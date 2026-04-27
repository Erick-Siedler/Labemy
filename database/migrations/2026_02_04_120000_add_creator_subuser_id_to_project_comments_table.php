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
        Schema::table('project_comments', function (Blueprint $table) {
            $table->foreignId('creator_subuser_id')
                ->nullable()
                ->after('creator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_comments', function (Blueprint $table) {
            $table->dropColumn('creator_subuser_id');
        });
    }
};
