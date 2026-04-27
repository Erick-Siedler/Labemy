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
        Schema::create('users_rel', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->onDelete('cascade');

            $table->foreignId('lab_id')
                  ->nullable()
                  ->constrained('labs')
                  ->onDelete('cascade');

            $table->foreignId('group_id')
                  ->nullable()
                  ->constrained('groups')
                  ->onDelete('cascade');

            $table->enum('status', ['pending', 'active', 'revoked'])->default('active');
            $table->enum('role', ['owner', 'teacher', 'assistant', 'student'])->default('student');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
                  
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id', 'lab_id', 'group_id'], 'users_rel_unique_membership');
            $table->index(['tenant_id', 'role', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_rel');
    }
};
