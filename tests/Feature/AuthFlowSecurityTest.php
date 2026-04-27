<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthFlowSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_terms_and_privacy_acceptance(): void
    {
        $response = $this->post('/tenant/register', [
            'name' => 'Usuario',
            'email' => 'usuario@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms_of_use' => 'on',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['privacy_policy']);
    }

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'status' => 'active',
            'plan' => 'free',
        ]);

        $response = $this->post('/tenant/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'status' => 'active',
            'plan' => 'free',
        ]);

        $this->post('/tenant/forgot-password', [
            'email' => $user->email,
        ])->assertStatus(302);

        $token = '';
        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $response = $this->post('/tenant/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-password-123', (string) $user->fresh()->password));
    }

    public function test_authenticated_user_is_logged_out_after_idle_timeout(): void
    {
        config(['session.idle_timeout' => 5]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'status' => 'active',
            'plan' => 'free',
        ]);

        $response = $this->actingAs($user, 'web')
            ->withSession([
                'last_activity_at' => now()->subMinutes(6)->getTimestamp(),
            ])
            ->get('/tenant/select');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Sua sessao expirou por inatividade. Faca login novamente.');
        $this->assertGuest('web');
    }
}
