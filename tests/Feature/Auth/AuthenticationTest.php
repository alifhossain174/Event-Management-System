<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create(['password' => 'Correct-password-123']);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Correct-password-123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'successful' => true,
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_user_is_rejected_with_a_generic_error_and_audit_history(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'Correct-password-123']);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Correct-password-123',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'successful' => false,
            'failure_reason' => 'inactive',
        ]);
    }

    public function test_an_inactive_authenticated_session_is_terminated(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_public_registration_route_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
