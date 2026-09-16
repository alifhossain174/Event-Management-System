<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = null;

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New-secure-password-123',
            'password_confirmation' => 'New-secure-password-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('New-secure-password-123', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password_reset',
            'subject_id' => $user->id,
        ]);
    }

    public function test_inactive_user_does_not_receive_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->inactive()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }
}
