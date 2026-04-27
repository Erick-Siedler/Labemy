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
        if (!Schema::hasTable('sub_user_invites')) {
            Schema::create('sub_user_invites', function (Blueprint $table) {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->onDelete('cascade');

                $table->foreignId('lab_id')
                    ->constrained('labs')
                    ->onDelete('cascade');

                $table->foreignId('group_id')
                    ->constrained('groups')
                    ->onDelete('cascade');

                $table->string('email');
                $table->enum('role', ['teacher', 'assistant', 'student'])->default('student');
                $table->foreignId('invited_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('accepted_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();

                $table->timestamps();
            });

            return;
        }

        Schema::table('sub_user_invites', function (Blueprint $table) {
            if (!Schema::hasColumn('sub_user_invites', 'role')) {
                $table->enum('role', ['teacher', 'assistant', 'student'])
                    ->default('student')
                    ->after('email');
            }

            if (!Schema::hasColumn('sub_user_invites', 'invited_by_user_id')) {
                $table->foreignId('invited_by_user_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('sub_user_invites', 'accepted_by_user_id')) {
                $table->foreignId('accepted_by_user_id')
                    ->nullable()
                    ->after('invited_by_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('sub_user_invites')) {
            return;
        }

        Schema::table('sub_user_invites', function (Blueprint $table) {
            if (Schema::hasColumn('sub_user_invites', 'accepted_by_user_id')) {
                $table->dropConstrainedForeignId('accepted_by_user_id');
            }

            if (Schema::hasColumn('sub_user_invites', 'invited_by_user_id')) {
                $table->dropConstrainedForeignId('invited_by_user_id');
            }

            if (Schema::hasColumn('sub_user_invites', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
