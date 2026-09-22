<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use LogicException;
use Tests\TestCase;

final class AuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_audit_service_redacts_sensitive_nested_metadata(): void
    {
        $administrator = $this->userWithRole('administrator');
        $request = Request::create('/sensitive', 'POST', server: [
            'REMOTE_ADDR' => '192.0.2.20',
            'HTTP_USER_AGENT' => 'Audit test agent',
        ]);

        $log = app(AuditService::class)->record('security.tested', $administrator, [
            'name' => 'Before',
            'password' => 'never-store-this',
            'provider' => ['api_key' => 'also-secret', 'enabled' => true],
        ], [
            'name' => 'After',
            'authorization_header' => 'Bearer credential',
        ], $administrator, $request);

        $this->assertSame('[REDACTED]', $log->before_values['password']);
        $this->assertSame('[REDACTED]', $log->before_values['provider']['api_key']);
        $this->assertSame('[REDACTED]', $log->after_values['authorization_header']);
        $this->assertStringNotContainsString('never-store-this', json_encode($log->toArray()));
        $this->assertSame('192.0.2.20', $log->ip_address);
    }

    public function test_audit_and_login_history_are_admin_filterable_but_unauthorized_users_are_denied(): void
    {
        $administrator = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        $log = app(AuditService::class)->record('users.reviewed', $staff, actor: $administrator);
        $history = LoginHistory::query()->create([
            'user_id' => $administrator->id,
            'email' => $administrator->email,
            'successful' => true,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test agent',
            'attempted_at' => now(),
        ]);

        $this->actingAs($administrator)->get(route('audit.index', ['action' => 'users.reviewed']))
            ->assertOk()->assertSee('users.reviewed');
        $this->actingAs($administrator)->get(route('audit.show', $log))->assertOk();
        $this->actingAs($administrator)->get(route('audit.logins.index', ['result' => 'success']))
            ->assertOk()->assertSee($administrator->email);
        $this->actingAs($administrator)->get(route('audit.logins.show', $history))->assertOk();

        $this->actingAs($staff)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('audit.show', $log))->assertForbidden();
        $this->actingAs($staff)->get(route('audit.logins.index'))->assertForbidden();
    }

    public function test_audit_and_login_records_cannot_be_updated_or_deleted_through_models(): void
    {
        $log = AuditLog::query()->create(['action' => 'append.only', 'occurred_at' => now()]);

        try {
            $log->update(['action' => 'tampered']);
            $this->fail('Audit update should have been blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'append.only']);
        }

        $history = LoginHistory::query()->create([
            'email' => 'test@example.test', 'successful' => false,
            'failure_reason' => 'invalid_credentials', 'attempted_at' => now(),
        ]);
        $this->expectException(LogicException::class);
        $history->delete();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->attach($role, ['assigned_at' => now()]);

        return $user->load('roles');
    }
}
