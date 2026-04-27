<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users_rel')) {
            return;
        }

        if (Schema::hasTable('subusers_rel')) {
            Schema::rename('subusers_rel', 'users_rel');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subusers_rel')) {
            return;
        }

        if (Schema::hasTable('users_rel')) {
            Schema::rename('users_rel', 'subusers_rel');
        }
    }
};
