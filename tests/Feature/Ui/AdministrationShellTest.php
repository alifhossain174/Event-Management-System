<?php

namespace Tests\Feature\Ui;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class AdministrationShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_administrator_sees_accessible_responsive_shell(): void
    {
        $administrator = $this->userWithRole('administrator');

        $this->actingAs($administrator)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Skip to main content')
            ->assertSee('Application navigation')
            ->assertSee('mobileSidebar')
            ->assertSee('Notifications, none unread')
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('confirmationModal')
            ->assertSee('Apply filters')
            ->assertSee('User accounts');
    }

    public function test_navigation_hides_unauthorized_items_without_replacing_server_authorization(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('users.index'));

        $this->actingAs($staff)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_style_guide_requires_authentication_and_is_available_in_testing(): void
    {
        $this->get(route('style-guide'))->assertRedirect(route('login'));

        $administrator = $this->userWithRole('administrator');

        $this->actingAs($administrator)
            ->get(route('style-guide'))
            ->assertOk()
            ->assertSee('UI style guide')
            ->assertSee('Local / testing only')
            ->assertSee('Status badges')
            ->assertSee('No records yet');
    }

    public function test_shared_components_escape_untrusted_output(): void
    {
        $html = Blade::render(
            '<x-ui.empty-state :title="$title" :description="$description"/>',
            [
                'title' => '<script>alert("unsafe")</script>',
                'description' => '<img src=x onerror=alert(1)>',
            ],
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function test_production_error_views_have_clear_recovery_actions(): void
    {
        foreach ([
            'errors.403' => ['403', 'Access denied'],
            'errors.404' => ['404', 'Page not found'],
            'errors.419' => ['419', 'Page expired'],
            'errors.500' => ['500', 'Something went wrong'],
        ] as $view => [$code, $title]) {
            $this->view($view)
                ->assertSee($code)
                ->assertSee($title)
                ->assertSee('Return home');
        }
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', $slug)->firstOrFail();
        $user->roles()->attach($role, ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
