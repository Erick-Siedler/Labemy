<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Lab;
use App\Models\SubUserInvite;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_registration_rate_limited_after_five_requests(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/tenant/register', [
                'name' => 'User ' . $i,
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'terms_of_use' => 'on',
                'privacy_policy' => 'on',
            ]);

            $response->assertStatus(302);
        }

        $response = $this->post('/tenant/register', [
            'name' => 'User 6',
            'email' => 'user6@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms_of_use' => 'on',
            'privacy_policy' => 'on',
        ]);

        $response->assertStatus(429);
    }

    public function test_subuser_registration_rate_limited_after_five_requests(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'status' => 'active',
            'plan' => 'free',
        ]);

        $tenant = Tenant::create([
            'creator_id' => $owner->id,
            'name' => 'Instituicao',
            'slug' => 'instituicao',
            'type' => 'school',
            'status' => 'active',
            'plan' => 'free',
            'trial_ends_at' => null,
            'settings' => [
                'max_storage_mb' => 1024,
                'max_labs' => 5,
                'max_users' => 100,
                'max_projects' => 50,
                'max_groups' => 20,
            ],
            'storage_used_mb' => 0,
        ]);

        $lab = Lab::create([
            'tenant_id' => $tenant->id,
            'creator_id' => $owner->id,
            'name' => 'Lab 1',
            'code' => 'lab-1',
            'status' => 'active',
        ]);

        $group = Group::create([
            'tenant_id' => $tenant->id,
            'lab_id' => $lab->id,
            'creator_id' => $owner->id,
            'name' => 'Group 1',
            'code' => 'group-1',
            'status' => 'active',
        ]);

        $token = Str::random(40);
        $email = 'student@example.com';

        SubUserInvite::create([
            'tenant_id' => $tenant->id,
            'lab_id' => $lab->id,
            'group_id' => $group->id,
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20']);

        $payload = [
            'name' => 'Student',
            'email' => $email,
            'password' => 'password123',
        ];

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post("/subuser/register/{$token}", $payload);
            $response->assertStatus(302);
        }

        $response = $this->post("/subuser/register/{$token}", $payload);
        $response->assertStatus(429);
    }
}
