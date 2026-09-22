<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\PromoCode;
use App\Models\RegistrationForm;
use App\Models\Role;
use App\Models\TicketType;
use App\Models\User;
use App\Services\EventModuleDataRegistry;
use App\Services\PaymentService;
use App\Services\TicketService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\FinanceCategorySeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TicketingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, FinanceCategorySeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_serialized_last_ticket_issuance_prevents_overselling_and_is_idempotent(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $type = $this->type($event, ['quantity_total' => 1]);
        $data = $this->issueData();
        $first = app(TicketService::class)->issue($event, $type, $data, $admin);
        $duplicate = app(TicketService::class)->issue($event, $type, $data, $admin);

        $this->assertTrue($first->is($duplicate));
        $this->assertDatabaseCount('tickets', 1);
        $this->assertSame('sold_out', $type->fresh()->status);

        try {
            app(TicketService::class)->issue($event, $type, array_replace($data, ['idempotency_key' => (string) Str::uuid()]), $admin);
            $this->fail('A second last-ticket issuance was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
    }

    public function test_promo_windows_ticket_usage_limits_and_deterministic_price_snapshots(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $freeType = $this->type($event, ['quantity_total' => 10, 'price' => '0.0000']);
        PromoCode::query()->create([
            'event_id' => $event->id, 'code' => 'EXPIRED', 'name' => 'Expired', 'discount_type' => 'fixed',
            'discount_value' => '1.0000', 'valid_from' => now()->subDays(2), 'valid_until' => now()->subDay(),
            'usage_limit' => 5, 'is_active' => true,
        ]);
        try {
            app(TicketService::class)->issue($event, $freeType, array_replace($this->issueData(), ['promo_code' => 'EXPIRED']), $admin);
            $this->fail('An expired Promo Code was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('promo_code', $exception->errors());
        }
        PromoCode::query()->create([
            'event_id' => $event->id, 'code' => 'LIMIT2', 'name' => 'Two tickets', 'discount_type' => 'percentage',
            'discount_value' => '50.0000', 'valid_from' => now()->subHour(), 'valid_until' => now()->addHour(),
            'usage_limit' => 2, 'is_active' => true,
        ]);
        $order = app(TicketService::class)->issue($event, $freeType, array_replace($this->issueData(), ['quantity' => 2, 'promo_code' => 'limit2']), $admin);
        $this->assertSame('0.0000', $order->total);
        $this->assertSame('LIMIT2', $order->promo_snapshot['code']);

        $this->expectException(ValidationException::class);
        app(TicketService::class)->issue($event, $freeType, array_replace($this->issueData(), ['promo_code' => 'LIMIT2']), $admin);
    }

    public function test_tokens_are_not_stored_plain_and_double_or_wrong_event_scans_are_safe(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $other = $this->event();
        $order = app(TicketService::class)->issue($event, $this->type($event), $this->issueData(), $admin);
        $ticket = $order->tickets->first();
        $token = $ticket->plainToken();

        $this->assertNotSame($token, $ticket->token_encrypted);
        $this->assertSame(hash('sha256', $token), $ticket->token_hash);
        $this->assertStringNotContainsString($token, json_encode($ticket->getAttributes()));

        try {
            app(TicketService::class)->validate($other, $token, $admin);
            $this->fail('Wrong-event scan was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('token', $exception->errors());
        }
        $first = app(TicketService::class)->validate($event, route('public.tickets.entry', ['token' => $token]), $admin);
        $second = app(TicketService::class)->validate($event, $token, $admin);
        $this->assertSame('validated', $first['state']);
        $this->assertSame('already_used', $second['state']);
        $this->assertSame($first['validation']->id, $second['validation']->id);
        $this->assertDatabaseCount('ticket_validations', 1);
    }

    public function test_free_refund_invalidates_ticket_and_restores_inventory_without_gateway_record(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $type = $this->type($event, ['quantity_total' => 1]);
        $order = app(TicketService::class)->issue($event, $type, $this->issueData(), $admin);
        $ticket = $order->tickets->first();
        $refund = app(TicketService::class)->refund($event, $ticket, $this->refundData(), $admin);

        $this->assertSame('free', $refund->method);
        $this->assertNull($refund->financial_refund_id);
        $this->assertSame('refunded', $ticket->fresh()->status);
        $this->assertSame('on_sale', $type->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.refunded', 'subject_id' => $refund->id]);

        $this->expectException(ValidationException::class);
        app(TicketService::class)->validate($event, $ticket->plainToken(), $admin);
    }

    public function test_priced_ticket_refund_creates_manual_financial_reversal_trace(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        EventModuleSetting::query()->updateOrCreate(['event_id' => $event->id, 'module_key' => 'payments'], ['is_enabled' => true, 'source' => 'manual']);
        $payment = app(PaymentService::class)->postPayment($event, [
            'idempotency_key' => (string) Str::uuid(), 'payment_type' => 'advance', 'amount' => '50.0000',
            'received_at' => now(), 'channel' => 'Cash', 'external_reference' => 'TICKET-CASH',
        ], [], $admin);
        $type = $this->type($event, ['price' => '50.0000']);
        $ticket = app(TicketService::class)->issue($event, $type, array_replace($this->issueData(), ['payment_id' => $payment->id]), $admin)->tickets->first();
        $refund = app(TicketService::class)->refund($event, $ticket, $this->refundData(), $admin);

        $this->assertSame('manual_payment_refund', $refund->method);
        $this->assertNotNull($refund->financial_refund_id);
        $this->assertDatabaseHas('refunds', ['id' => $refund->financial_refund_id, 'payment_id' => $payment->id, 'amount' => 50]);
        $this->assertDatabaseHas('event_income_entries', ['source_type' => 'payment_refund', 'source_id' => $refund->financial_refund_id, 'is_reversal' => true]);
    }

    public function test_permissions_publication_module_guard_and_registration_independence(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        $frontDesk = $this->userWithRole('front-desk-check-in');
        $event = $this->event();
        $type = $this->type($event, ['is_public' => true]);
        $order = app(TicketService::class)->issue($event, $type, $this->issueData(), $admin);
        $ticket = $order->tickets->first();

        $this->actingAs($admin)->get(route('events.tickets.index', $event))->assertOk()->assertSee('Ticketing');
        $this->actingAs($staff)->get(route('events.tickets.index', $event))->assertForbidden();
        $this->actingAs($frontDesk)->get(route('events.tickets.validate', $event))->assertOk();
        $this->get(route('public.tickets.entry', ['token' => $ticket->plainToken()]))->assertNotFound();
        app(TicketService::class)->publish($event, true, $admin);
        $this->get(route('public.tickets.catalogue', $event->fresh()->ticketing_public_slug))->assertOk()->assertSee($type->name);
        $this->get(route('public.tickets.entry', ['token' => $ticket->plainToken()]))->assertOk()->assertSee($ticket->ticket_number);
        $this->get(route('public.tickets.qr', ['token' => $ticket->plainToken()]))->assertOk()->assertHeader('content-type', 'image/svg+xml');
        $this->actingAs($admin)->post(route('events.ticket-orders.email', [$event, $order]), [
            'recipient_address' => 'holder@example.test', 'recipient_name' => 'Ticket Holder',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $messageBody = $order->outboundMessages()->firstOrFail()->body;
        $this->assertStringNotContainsString($ticket->plainToken(), $messageBody);
        $this->assertStringContainsString($order->reference_number, $messageBody);

        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'ticketing'));
        $event->moduleSettings()->where('module_key', 'ticketing')->update(['is_enabled' => false]);
        $this->actingAs($admin)->get(route('events.tickets.index', $event))->assertRedirect(route('events.modules.edit', $event));
        $this->get(route('public.tickets.entry', ['token' => $ticket->plainToken()]))->assertNotFound();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
        $this->assertDatabaseHas('event_module_settings', ['event_id' => $event->id, 'module_key' => 'registration', 'is_enabled' => true]);
        $form = RegistrationForm::query()->create([
            'event_id' => $event->id, 'name' => 'Independent Registration', 'public_slug' => Str::random(48),
            'duplicate_policy' => 'allow', 'approval_required' => true, 'confirmation_channel' => 'none',
            'is_active' => true, 'published_at' => now(),
        ]);
        $this->get(route('public.registrations.show', $form->public_slug))->assertOk()->assertSee('Independent Registration');
    }

    private function event(): Event
    {
        $event = Event::factory()->create(['status' => 'planning', 'currency_code' => 'USD']);
        foreach (['ticketing', 'registration'] as $key) {
            EventModuleSetting::query()->updateOrCreate(['event_id' => $event->id, 'module_key' => $key], ['is_enabled' => true, 'source' => 'manual']);
        }

        return $event->fresh('client');
    }

    /** @param array<string, mixed> $overrides */
    private function type(Event $event, array $overrides = []): TicketType
    {
        return TicketType::query()->create(array_replace([
            'event_id' => $event->id, 'name' => 'Regular', 'code' => 'REGULAR-'.Str::upper(Str::random(6)),
            'category' => 'regular', 'quantity_total' => 5, 'price' => '0.0000',
            'currency_code' => 'USD', 'status' => 'on_sale', 'is_public' => false,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function issueData(): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'attendee_name' => 'Ticket Holder', 'attendee_email' => 'holder@example.test', 'quantity' => 1, 'source' => 'manual'];
    }

    /** @return array<string, mixed> */
    private function refundData(): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'reason' => 'Manager-approved correction', 'refunded_at' => now(), 'channel' => 'Cash'];
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
