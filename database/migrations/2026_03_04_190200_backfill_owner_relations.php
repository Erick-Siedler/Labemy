<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users_rel') || !Schema::hasTable('tenants')) {
            return;
        }

        $tenants = DB::table('tenants')
            ->select(['id', 'creator_id'])
            ->whereNotNull('creator_id')
            ->get();

        foreach ($tenants as $tenant) {
            $exists = DB::table('users_rel')
                ->where('user_id', (int) $tenant->creator_id)
                ->where('tenant_id', (int) $tenant->id)
                ->whereNull('lab_id')
                ->whereNull('group_id')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('users_rel')->insert([
                'user_id' => (int) $tenant->creator_id,
                'tenant_id' => (int) $tenant->id,
                'lab_id' => null,
                'group_id' => null,
                'status' => 'active',
                'role' => 'owner',
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
        if (!Schema::hasTable('users_rel') || !Schema::hasTable('tenants')) {
            return;
        }

        $tenantCreatorRows = DB::table('tenants')
            ->select(['id', 'creator_id'])
            ->whereNotNull('creator_id')
            ->get();

        foreach ($tenantCreatorRows as $row) {
            DB::table('users_rel')
                ->where('user_id', (int) $row->creator_id)
                ->where('tenant_id', (int) $row->id)
                ->whereNull('lab_id')
                ->whereNull('group_id')
                ->where('role', 'owner')
                ->delete();
        }
    }
};
