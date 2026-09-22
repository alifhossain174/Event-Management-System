<?php

namespace Tests\Feature\Finance;

use App\Mail\InvoiceMail;
use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Role;
use App\Models\User;
use App\Services\EventModuleDataRegistry;
use App\Services\InvoiceBalanceService;
use App\Services\InvoiceDeliveryService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\FinanceCategorySeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, FinanceCategorySeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_discount_precedes_tax_and_issued_snapshot_is_immutable(): void
    {
        $admin = $this->userWithRole('administrator');
        $invoice = $this->draft($this->event(), $admin, [[
            'description' => 'Event package', 'quantity' => '3.0000', 'unit_price' => '19.9950',
            'discount_type' => 'percentage', 'discount_value' => '10.000000', 'tax_rate' => '7.500000',
        ]]);

        $this->assertSame('59.9850', $invoice->subtotal);
        $this->assertSame('5.9985', $invoice->discount_total);
        $this->assertSame('53.9865', $invoice->taxable_total);
        $this->assertSame('4.0490', $invoice->tax_total);
        $this->assertSame('58.0355', $invoice->total);
        app(InvoiceService::class)->issue($invoice, $admin);

        $this->expectException(LogicException::class);
        $invoice->refresh()->update(['subject' => 'Changed after issue']);
    }

    public function test_numbering_uses_locked_year_sequence_and_event_needs_no_booking(): void
    {
        $admin = $this->userWithRole('administrator');
        $firstEvent = $this->event();
        $secondEvent = $this->event();
        $first = app(InvoiceService::class)->issue($this->draft($firstEvent, $admin), $admin);
        $second = app(InvoiceService::class)->issue($this->draft($secondEvent, $admin), $admin);

        $this->assertNull($firstEvent->booking_id);
        $this->assertMatchesRegularExpression('/^INV-'.now()->format('Y').'-\d{6}$/', $first->invoice_number);
        $this->assertNotSame($first->invoice_number, $second->invoice_number);
        $this->assertSame(1, InvoiceSequence::query()->count());
        $this->assertSame(3, InvoiceSequence::query()->value('next_value'));
        $this->assertTrue(collect(Schema::getIndexes('invoice_sequences'))->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === ['scope_key', 'prefix', 'sequence_year'],
        ));
        $this->assertTrue(collect(Schema::getIndexes('invoices'))->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === ['invoice_number'],
        ));
    }

    public function test_payments_reconcile_partial_paid_refund_and_unallocated_overpayment(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $invoice = app(InvoiceService::class)->issue($this->draft($event, $admin, [[
            'description' => 'Package', 'quantity' => '1.0000', 'unit_price' => '100.0000',
            'discount_type' => 'none', 'discount_value' => '0', 'tax_rate' => '0',
        ]]), $admin);
        $payments = app(PaymentService::class);
        $first = $payments->postPayment($event, $this->paymentData('40.0000'), [['invoice_id' => $invoice->id, 'amount' => '40.0000']], $admin);
        $this->assertSame('partially_paid', $invoice->refresh()->status);
        $this->assertSame('60.0000', app(InvoiceBalanceService::class)->balance($invoice));
        $second = $payments->postPayment($event, $this->paymentData('80.0000'), [['invoice_id' => $invoice->id, 'amount' => '60.0000']], $admin);
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame('20.0000', $payments->unallocatedRefundable($second));
        $allocation = $first->allocations->first();
        $payments->refund($first, [
            'idempotency_key' => (string) Str::uuid(), 'amount' => '10.0000',
            'refunded_at' => now(), 'payment_allocation_id' => $allocation->id,
            'channel' => 'Cash', 'reason' => 'Correction',
        ], $admin);
        $this->assertSame('partially_paid', $invoice->refresh()->status);
        $this->assertSame('10.0000', app(InvoiceBalanceService::class)->balance($invoice));
    }

    public function test_invoice_allocation_cannot_exceed_balance(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $invoice = app(InvoiceService::class)->issue($this->draft($event, $admin), $admin);

        $this->expectException(ValidationException::class);
        app(PaymentService::class)->postPayment($event, $this->paymentData('101.0000'), [['invoice_id' => $invoice->id, 'amount' => '101.0000']], $admin);
    }

    public function test_cancel_and_credit_are_traceable_and_credit_preserves_negative_snapshot(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $draft = $this->draft($event, $admin);
        app(InvoiceService::class)->cancel($draft, 'Client withdrew before issue', $admin);
        $this->assertSame('cancelled', $draft->refresh()->status);

        $issued = app(InvoiceService::class)->issue($this->draft($event, $admin), $admin);
        $credit = app(InvoiceService::class)->credit($issued, 'Corrected service scope', $admin);
        $this->assertSame('credited', $issued->refresh()->status);
        $this->assertSame('credit_note', $credit->document_type);
        $this->assertSame('-100.0000', $credit->total);
        $this->assertSame($issued->id, $credit->credit_for_invoice_id);
        $this->assertDatabaseHas('invoice_status_histories', ['invoice_id' => $issued->id, 'to_status' => 'credited']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.credited', 'subject_id' => $issued->id]);
    }

    public function test_pdf_print_email_delivery_and_failure_logging(): void
    {
        Mail::fake();
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $invoice = app(InvoiceService::class)->issue($this->draft($event, $admin), $admin);
        app(InvoiceDeliveryService::class)->email($invoice, 'billing@example.test', $admin);
        Mail::assertSent(InvoiceMail::class);
        $this->assertDatabaseHas('invoice_deliveries', ['invoice_id' => $invoice->id, 'recipient' => 'billing@example.test', 'status' => 'sent']);

        $response = $this->actingAs($admin)->get(route('events.invoices.pdf', [$event, $invoice]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->view('invoices.pdf', ['invoice' => $invoice->load('items'), 'applied' => '0.0000', 'balance' => $invoice->total])->assertSee('100.0000');
        $this->actingAs($admin)->get(route('events.invoices.print', [$event, $invoice]))->assertOk()->assertSee($invoice->invoice_number);

        $failing = Mockery::mock(Mailer::class);
        $failing->shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP offline'));
        $this->app->instance(Mailer::class, $failing);
        try {
            app(InvoiceDeliveryService::class)->email($invoice, 'failed@example.test', $admin);
            $this->fail('A failed synchronous delivery should report validation feedback.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('invoice_deliveries', ['invoice_id' => $invoice->id, 'recipient' => 'failed@example.test', 'status' => 'failed']);
        }
    }

    public function test_authorization_search_and_disabled_module_preserve_invoice_data(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        $event = $this->event();
        $invoice = app(InvoiceService::class)->issue($this->draft($event, $admin), $admin);
        $this->actingAs($admin)->get(route('events.invoices.index', $event))->assertOk()->assertSee($invoice->invoice_number);
        $this->actingAs($admin)->get(route('search', ['q' => substr($invoice->invoice_number, 0, 8)]))->assertOk()->assertSee($invoice->invoice_number);
        $this->actingAs($staff)->get(route('events.invoices.show', [$event, $invoice]))->assertForbidden();
        $event->moduleSettings()->where('module_key', 'invoices')->update(['is_enabled' => false]);
        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'invoices'));
        $this->actingAs($admin)->get(route('events.invoices.show', [$event, $invoice]))->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    private function event(): Event
    {
        $event = Event::factory()->create(['status' => 'planning', 'currency_code' => 'USD', 'booking_id' => null]);
        foreach (['invoices', 'payments'] as $key) {
            EventModuleSetting::query()->updateOrCreate(['event_id' => $event->id, 'module_key' => $key], ['is_enabled' => true, 'source' => 'manual']);
        }

        return $event->fresh(['client', 'moduleSettings']);
    }

    private function draft(Event $event, User $actor, ?array $items = null): Invoice
    {
        return app(InvoiceService::class)->createDraft($event, [
            'due_date' => today()->addDays(14)->toDateString(), 'subject' => 'Event services',
            'tax_label' => 'Tax', 'default_tax_rate' => '0.000000',
        ], $items ?? [[
            'description' => 'Event services', 'quantity' => '1.0000', 'unit_price' => '100.0000',
            'discount_type' => 'none', 'discount_value' => '0', 'tax_rate' => '0',
        ]], $actor);
    }

    private function paymentData(string $amount): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'payment_type' => 'partial', 'amount' => $amount, 'received_at' => now(), 'channel' => 'Cash'];
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
