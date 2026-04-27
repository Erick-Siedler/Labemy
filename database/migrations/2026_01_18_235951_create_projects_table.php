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
        Schema::create('projects', function (Blueprint $table) {
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

            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'approved', 'rejected', 'archived']);
            $table->enum('visibility', ['public', 'private']);
            $table->bigInteger('current_version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
