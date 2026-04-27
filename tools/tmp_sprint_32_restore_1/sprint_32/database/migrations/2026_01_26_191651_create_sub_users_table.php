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
        Schema::create('sub_users', function (Blueprint $table) {
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
                  
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->enum('role', ['teacher', 'student', 'assistant'])->default('student');
            $table->string('phone')->nullable();
            $table->string('institution')->nullable();
            $table->text('bio')->nullable();
            $table->json('preferences')->nullable();
            $table->json('notifications')->nullable();
            $table->string('profile_photo_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_users');
    }
};
