<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sub_users') || !Schema::hasTable('users') || !Schema::hasTable('users_rel')) {
            return;
        }

        $hasTenantColumn = Schema::hasColumn('sub_users', 'tenant_id');
        $hasLabColumn = Schema::hasColumn('sub_users', 'lab_id');
        $hasGroupColumn = Schema::hasColumn('sub_users', 'group_id');
        $hasPhoneColumn = Schema::hasColumn('sub_users', 'phone');
        $hasInstitutionColumn = Schema::hasColumn('sub_users', 'institution');
        $hasBioColumn = Schema::hasColumn('sub_users', 'bio');
        $hasPreferencesColumn = Schema::hasColumn('sub_users', 'preferences');
        $hasNotificationsColumn = Schema::hasColumn('sub_users', 'notifications');
        $hasPasswordColumn = Schema::hasColumn('sub_users', 'password');

        $subUsers = DB::table('sub_users')->get();

        foreach ($subUsers as $subUser) {
            $email = (string) ($subUser->email ?? '');
            if ($email === '') {
                continue;
            }

            $user = DB::table('users')->where('email', $email)->first();
            if (!$user) {
                $plainPassword = $hasPasswordColumn ? (string) ($subUser->password ?? '') : '';
                $passwordToStore = $plainPassword !== '' ? $plainPassword : Hash::make(Str::random(24));

                $userId = DB::table('users')->insertGetId([
                    'name' => (string) ($subUser->name ?? 'Usuario'),
                    'email' => $email,
                    'password' => $passwordToStore,
                    'role' => 'owner',
                    'status' => 'active',
                    'phone' => $hasPhoneColumn ? ($subUser->phone ?? null) : null,
                    'institution' => $hasInstitutionColumn ? ($subUser->institution ?? null) : null,
                    'plan' => 'free',
                    'trial_used' => false,
                    'bio' => $hasBioColumn ? ($subUser->bio ?? null) : null,
                    'preferences' => $hasPreferencesColumn ? ($subUser->preferences ?? null) : null,
                    'notifications' => $hasNotificationsColumn ? ($subUser->notifications ?? null) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $user = DB::table('users')->where('id', $userId)->first();
            }

            $labId = $hasLabColumn ? ($subUser->lab_id ?? null) : null;
            $groupId = $hasGroupColumn ? ($subUser->group_id ?? null) : null;
            $tenantId = $hasTenantColumn ? ($subUser->tenant_id ?? null) : null;

            if (empty($tenantId) && !empty($labId) && Schema::hasTable('labs')) {
                $tenantId = DB::table('labs')->where('id', (int) $labId)->value('tenant_id');
            }

            if (empty($tenantId) && !empty($groupId) && Schema::hasTable('groups')) {
                $tenantId = DB::table('groups')->where('id', (int) $groupId)->value('tenant_id');
            }

            if (empty($tenantId)) {
                continue;
            }

            $role = (string) ($subUser->role ?? 'student');
            if (!in_array($role, ['owner', 'teacher', 'assistant', 'student'], true)) {
                $role = $role === 'asssitant' || $role === 'assitant' ? 'assistant' : 'student';
            }

            $exists = DB::table('users_rel')
                ->where('user_id', (int) $user->id)
                ->where('tenant_id', (int) $tenantId)
                ->where('lab_id', $labId ? (int) $labId : null)
                ->where('group_id', $groupId ? (int) $groupId : null)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('users_rel')->insert([
                'user_id' => (int) $user->id,
                'tenant_id' => (int) $tenantId,
                'lab_id' => $labId ? (int) $labId : null,
                'group_id' => $groupId ? (int) $groupId : null,
                'status' => 'active',
                'role' => $role,
                'accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migracao de dados sem rollback destrutivo.
    }
};
