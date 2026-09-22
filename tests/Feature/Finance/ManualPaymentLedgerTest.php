<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\Income;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\EventModuleDataRegistry;
use App\Services\FinanceService;
use App\Services\PaymentService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\FinanceCategorySeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ManualPaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            OrganizationSettingsSeeder::class,
            FinanceCategorySeeder::class,
            EventConfigurationSeeder::class,
        ]);
    }

    public function test_multiple_installments_allocate_exactly_and_overpayment_remains_unallocated_advance(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $service = app(PaymentService::class);
        $schedule = $service->createSchedule($event, [
            'label' => 'Event balance', 'amount_due' => '300.0000', 'due_date' => '2026-10-01',
        ], $admin);

        $first = $service->postPayment($event, $this->paymentData('100.0000', 'installment'), [
            ['payment_schedule_id' => $schedule->id, 'amount' => '100.0000'],
        ], $admin);
        $second = $service->postPayment($event, $this->paymentData('250.0000', 'final'), [
            ['payment_schedule_id' => $schedule->id, 'amount' => '200.0000'],
        ], $admin);

        $this->assertNotSame($first->receipt_number, $second->receipt_number);
        $this->assertSame('paid', $schedule->refresh()->status);
        $summary = $service->eventSummary($event);
        $this->assertSame('300.0000', $summary['scheduled']);
        $this->assertSame('0.0000', $summary['outstanding']);
        $this->assertSame('350.0000', $summary['received']);
        $this->assertSame('50.0000', $summary['unallocated']);
        $this->assertSame('350.0000', app(FinanceService::class)->summary($event)['actual_income']);
        $this->assertSame(2, Income::query()->where('source_type', 'payment')->where('status', 'posted')->count());
    }

    public function test_allocated_refund_reopens_due_preserves_original_and_limits_total_refund(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $service = app(PaymentService::class);
        $schedule = $service->createSchedule($event, [
            'label' => 'Deposit', 'amount_due' => '100.0000', 'due_date' => '2026-10-01',
        ], $admin);
        $payment = $service->postPayment($event, $this->paymentData('100.0000'), [
            ['payment_schedule_id' => $schedule->id, 'amount' => '100.0000'],
        ], $admin);
        $allocation = $payment->allocations->first();
        $refund = $service->refund($payment, $this->refundData('20.0000', $allocation->id), $admin);

        $this->assertSame('100.0000', $payment->refresh()->amount);
        $this->assertSame('partially_paid', $schedule->refresh()->status);
        $this->assertSame('20.0000', $service->scheduleOutstanding($schedule));
        $this->assertSame('80.0000', app(FinanceService::class)->summary($event)['actual_income']);
        $this->assertDatabaseHas('event_income_entries', [
            'source_type' => 'payment_refund', 'source_id' => $refund->id, 'is_reversal' => true, 'status' => 'posted',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.refunded', 'subject_id' => $refund->id]);

        $this->expectException(ValidationException::class);
        $service->refund($payment, $this->refundData('81.0000', $allocation->id), $admin);
    }

    public function test_unallocated_advance_refund_consumes_only_unallocated_credit(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $service = app(PaymentService::class);
        $schedule = $service->createSchedule($event, [
            'label' => 'Deposit', 'amount_due' => '100.0000', 'due_date' => '2026-10-01',
        ], $admin);
        $payment = $service->postPayment($event, $this->paymentData('150.0000', 'advance'), [
            ['payment_schedule_id' => $schedule->id, 'amount' => '100.0000'],
        ], $admin);
        $service->refund($payment, $this->refundData('30.0000'), $admin);

        $this->assertSame('20.0000', $service->unallocatedRefundable($payment));
        $this->assertSame('0.0000', $service->scheduleOutstanding($schedule));
        $this->assertSame('120.0000', $service->eventSummary($event)['net_received']);

        $this->expectException(ValidationException::class);
        $service->refund($payment, $this->refundData('21.0000'), $admin);
    }

    public function test_duplicate_submission_is_idempotent_and_receipt_is_unique(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $service = app(PaymentService::class);
        $data = $this->paymentData('75.0000');
        $first = $service->postPayment($event, $data, [], $admin);
        $second = $service->postPayment($event, $data, [], $admin);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, Income::query()->where('source_type', 'payment')->count());
        $this->assertDatabaseHas('status_histories', [
            'subject_type' => (new Payment)->getMorphClass(), 'subject_id' => $first->id,
            'from_status' => 'draft', 'to_status' => 'posted',
        ]);
    }

    public function test_zero_and_negative_payments_are_rejected(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();

        foreach (['0', '-1.0000'] as $amount) {
            try {
                app(PaymentService::class)->postPayment($event, $this->paymentData($amount), [], $admin);
                $this->fail('Non-positive payment amount was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseMissing('payments', ['amount' => $amount]);
            }
        }

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_locked_schedule_recheck_rejects_a_second_allocation_after_due_is_satisfied(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $service = app(PaymentService::class);
        $schedule = $service->createSchedule($event, [
            'label' => 'Only due', 'amount_due' => '50.0000', 'due_date' => '2026-10-01',
        ], $admin);
        $service->postPayment($event, $this->paymentData('50.0000'), [
            ['payment_schedule_id' => $schedule->id, 'amount' => '50.0000'],
        ], $admin);

        $this->expectException(ValidationException::class);
        $service->postPayment($event, $this->paymentData('1.0000'), [
            ['payment_schedule_id' => $schedule->id, 'amount' => '1.0000'],
        ], $admin);
    }

    public function test_invoice_math_returns_zero_allocated_for_unknown_invoice(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $payment = app(PaymentService::class)->postPayment($event, $this->paymentData('30.0000'), [], $admin);
        $this->assertSame('0.0000', app(PaymentService::class)->invoiceAllocatedAmount(42));
        $this->assertSame('100.0000', app(PaymentService::class)->invoiceDue(42, '100.0000'));
        $this->assertTrue(Schema::hasTable('invoices'));
        $this->assertSame('30.0000', app(PaymentService::class)->unallocatedRefundable($payment));
    }

    public function test_permissions_module_guard_history_screens_and_no_gateway_routes(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        $event = $this->event();
        $service = app(PaymentService::class);
        $schedule = $service->createSchedule($event, [
            'label' => 'Deposit', 'amount_due' => '100.0000', 'due_date' => '2026-10-01',
        ], $admin);
        $payment = $service->postPayment($event, $this->paymentData('25.0000'), [
            ['payment_schedule_id' => $schedule->id, 'amount' => '25.0000'],
        ], $admin);

        $this->actingAs($admin)->get(route('events.payments.index', $event))->assertOk()->assertSee($payment->receipt_number);
        $this->actingAs($admin)->get(route('events.payments.receipt', [$event, $payment]))->assertOk()->assertSee('Payment receipt');
        $this->actingAs($admin)->get(route('clients.payments', $event->client))->assertOk()->assertSee($payment->receipt_number);
        $this->actingAs($admin)->get(route('payments.due'))->assertOk()->assertSee('Payment schedules and dues');
        $this->actingAs($staff)->get(route('events.payments.index', $event))->assertForbidden();

        $event->moduleSettings()->where('module_key', 'payments')->update(['is_enabled' => false]);
        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'payments'));
        $this->actingAs($admin)->get(route('events.payments.index', $event))
            ->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);

        $routeNames = collect(Route::getRoutes())->map(fn ($route) => (string) $route->getName())->filter()->join(' ');
        $routeUris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->join(' ');
        $this->assertStringNotContainsString('checkout', $routeNames.' '.$routeUris);
        $this->assertStringNotContainsString('webhook', $routeNames.' '.$routeUris);
        $this->assertStringNotContainsString('gateway', $routeNames.' '.$routeUris);
    }

    private function event(): Event
    {
        $event = Event::factory()->create(['status' => 'planning', 'currency_code' => 'USD']);
        EventModuleSetting::query()->updateOrCreate(
            ['event_id' => $event->id, 'module_key' => 'payments'],
            ['is_enabled' => true, 'source' => 'manual'],
        );

        return $event->fresh('client');
    }

    private function paymentData(string $amount, string $type = 'partial'): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(), 'payment_type' => $type,
            'amount' => $amount, 'received_at' => '2026-09-19 10:00:00',
            'channel' => 'Cash', 'external_reference' => 'MANUAL-REF',
        ];
    }

    private function refundData(string $amount, ?int $allocationId = null): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(), 'amount' => $amount,
            'refunded_at' => '2026-09-20 10:00:00', 'payment_allocation_id' => $allocationId,
            'channel' => 'Cash', 'reason' => 'Client-approved correction',
        ];
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
