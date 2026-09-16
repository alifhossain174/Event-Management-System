<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_and_password(): void
    {
        $user = User::factory()->create(['password' => 'Old-password-123']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
        ])->assertSessionHas('status');

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'Old-password-123',
            'password' => 'New-password-123',
            'password_confirmation' => 'New-password-123',
        ])->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.test', $user->email);
        $this->assertTrue(Hash::check('New-password-123', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.profile_updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_changed']);
    }
}
