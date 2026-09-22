<?php

namespace Tests\Feature\Communications;

use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\InAppNotification;
use App\Models\MessageTemplate;
use App\Models\NotificationRecipient;
use App\Models\OutboundMessage;
use App\Models\Role;
use App\Models\User;
use App\Services\EventModuleDataRegistry;
use App\Services\NotificationService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class CommunicationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_notification_inbox_is_recipient_isolated_and_supports_read_unread(): void
    {
        $first = $this->userWithRole('staff');
        $second = $this->userWithRole('staff');
        app(NotificationService::class)->sendToUsers([$first, $second], [
            'type' => 'assignment', 'title' => 'Private assignment', 'body' => '<script>alert(1)</script>',
            'route_name' => 'notifications.index', 'route_parameters' => [], 'idempotency_key' => 'isolation-test',
        ]);
        $firstRecipient = NotificationRecipient::query()->where('user_id', $first->id)->firstOrFail();
        $secondRecipient = NotificationRecipient::query()->where('user_id', $second->id)->firstOrFail();

        $this->actingAs($first)->get(route('notifications.index'))->assertOk()->assertSee('Private assignment')->assertSee('&lt;script&gt;', false);
        $this->actingAs($first)->patch(route('notifications.read', $secondRecipient))->assertForbidden();
        $this->actingAs($first)->patch(route('notifications.read', $firstRecipient))->assertRedirect(route('notifications.index'));
        $this->assertNotNull($firstRecipient->refresh()->read_at);
        $this->assertNull($secondRecipient->refresh()->read_at);
        $this->actingAs($first)->patch(route('notifications.unread', $firstRecipient))->assertRedirect();
        $this->assertNull($firstRecipient->refresh()->read_at);
    }

    public function test_arbitrary_notification_links_are_rejected_before_storage(): void
    {
        $user = $this->userWithRole('administrator');

        try {
            app(NotificationService::class)->sendToUsers([$user], [
                'type' => 'unsafe', 'title' => 'Unsafe', 'route_name' => 'login',
                'route_parameters' => [], 'idempotency_key' => 'unsafe-link',
            ]);
            $this->fail('An unapproved notification destination should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseMissing('notifications', ['idempotency_key' => 'unsafe-link']);
        }
    }

    public function test_email_is_sent_synchronously_and_marketing_requires_consent(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->eventWithCommunications();

        $this->actingAs($admin)->post(route('events.communications.store', $event), $this->messagePayload())
            ->assertSessionDoesntHaveErrors();
        $message = OutboundMessage::query()->firstOrFail();
        $this->assertSame('sent', $message->status);
        $this->assertSame(1, $message->attempt_count);
        $this->assertDatabaseHas('delivery_logs', ['outbound_message_id' => $message->id, 'status' => 'sent', 'provider' => 'laravel-mail']);

        $this->actingAs($admin)->post(route('events.communications.store', $event), $this->messagePayload([
            'category' => 'marketing', 'idempotency_key' => (string) Str::uuid(),
        ]))->assertSessionHasErrors('marketing_consent_confirmed');
        $this->assertDatabaseCount('outbound_messages', 1);
    }

    public function test_disabled_provider_fails_gracefully_and_retry_preserves_attempt_history(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->eventWithCommunications();
        $payload = $this->messagePayload(['channel' => 'sms', 'recipient_address' => '+491234567890']);

        $this->actingAs($admin)->post(route('events.communications.store', $event), $payload)->assertSessionHas('warning');
        $message = OutboundMessage::query()->firstOrFail();
        $this->assertSame('failed', $message->status);
        $this->assertDatabaseHas('delivery_logs', ['outbound_message_id' => $message->id, 'status' => 'failed', 'sanitized_error' => 'provider_unavailable']);
        $this->assertDatabaseMissing('delivery_logs', ['outbound_message_id' => $message->id, 'sanitized_error' => 'SMS delivery is disabled until a provider is configured.']);

        $this->actingAs($admin)->post(route('events.communications.retry', [$event, $message]))->assertSessionHas('warning');
        $this->assertSame([1, 2], $message->deliveryLogs()->orderBy('attempt_number')->pluck('attempt_number')->all());
    }

    public function test_template_content_is_snapshotted_and_later_edits_do_not_rewrite_messages(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->eventWithCommunications();
        $this->actingAs($admin)->post(route('message-templates.store'), [
            'key' => 'event-update', 'name' => 'Event update', 'channel' => 'email',
            'category' => 'operational', 'subject' => 'Original subject', 'body' => 'Original body', 'is_active' => '1',
        ])->assertSessionDoesntHaveErrors();
        $template = MessageTemplate::query()->firstOrFail();
        $payload = $this->messagePayload(['message_template_id' => $template->id]);
        unset($payload['subject'], $payload['body']);

        $this->actingAs($admin)->post(route('events.communications.store', $event), $payload)->assertSessionDoesntHaveErrors();
        $message = OutboundMessage::query()->firstOrFail();
        $template->update(['subject' => 'Changed subject', 'body' => 'Changed body']);

        $this->assertSame('Original subject', $message->refresh()->subject);
        $this->assertSame('Original body', $message->body);
    }

    public function test_due_event_reminders_are_idempotent_and_run_without_queue_workers(): void
    {
        $manager = $this->userWithRole('event-manager');
        $event = Event::factory()->create(['status' => 'planning', 'manager_user_id' => $manager->id, 'starts_at' => now()->addHours(4), 'ends_at' => now()->addHours(8)]);

        $this->assertSame(0, Artisan::call('notifications:send-due-reminders'));
        $this->assertSame(0, Artisan::call('notifications:send-due-reminders'));
        $this->assertSame(1, InAppNotification::query()->where('type', 'event_reminder')->where('event_id', $event->id)->count());
        $this->assertDatabaseHas('notification_recipients', ['user_id' => $manager->id, 'read_at' => null]);
    }

    public function test_communications_are_authorized_guarded_and_preserved_when_module_is_disabled(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        $event = $this->eventWithCommunications();
        $this->actingAs($admin)->post(route('events.communications.store', $event), $this->messagePayload())->assertSessionDoesntHaveErrors();
        $message = OutboundMessage::query()->firstOrFail();

        $this->actingAs($staff)->get(route('events.communications.index', $event))->assertForbidden();
        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'communications'));
        $event->moduleSettings()->where('module_key', 'communications')->update(['is_enabled' => false]);
        $this->actingAs($admin)->get(route('events.communications.index', $event))->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseHas('outbound_messages', ['id' => $message->id]);
        $event->moduleSettings()->where('module_key', 'communications')->update(['is_enabled' => true]);
        $this->actingAs($admin)->get(route('events.communications.show', [$event, $message]))->assertOk()->assertSee('Accepted by transport');
    }

    private function eventWithCommunications(): Event
    {
        $event = Event::factory()->create(['status' => 'planning']);
        EventModuleSetting::query()->updateOrCreate(
            ['event_id' => $event->id, 'module_key' => 'communications'],
            ['is_enabled' => true, 'source' => 'manual'],
        );

        return $event->fresh(['client', 'moduleSettings']);
    }

    private function messagePayload(array $overrides = []): array
    {
        return array_replace([
            'channel' => 'email', 'category' => 'operational',
            'recipient_address' => 'client@example.test', 'recipient_name' => 'Client',
            'subject' => 'Event update', 'body' => 'Your Event plan has been updated.',
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
