<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('source', 64)->nullable()->after('type');
            $table->string('reference_type', 64)->nullable()->after('source');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->softDeletes();

            $table->index(
                ['table', 'user_id', 'source', 'reference_type', 'reference_id'],
                'notifications_ref_lookup_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_ref_lookup_idx');
            $table->dropColumn(['source', 'reference_type', 'reference_id']);
            $table->dropSoftDeletes();
        });
    }
};

