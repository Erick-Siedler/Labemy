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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->OnDelete('cascade');

            $table->foreignId('lab_id')
                ->constrained('labs')
                ->OnDelete('cascade');

            $table->foreignId('created_by')
                ->constrained('users')
                ->OnDelete('cascade');

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('color');

            $table->timestamp('due');

            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
