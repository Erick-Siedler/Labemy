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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')
                  ->constrained('users')
                  ->onDelete('cascade');
                  
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['school', 'college', 'technical_school', 'company', 'other']);
            $table->enum('status', ['active', 'suspended', 'pending', 'archived']);
            $table->enum('plan', ['free', 'solo', 'pro', 'enterprise']);
            $table->date('trial_ends_at')->nullable();
            $table->json('settings')->nullable();
            $table->bigInteger('storage_used_mb')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
