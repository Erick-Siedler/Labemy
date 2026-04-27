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
        Schema::create('project_versions', function (Blueprint $table) {
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

            $table->foreignId('project_id')
                  ->constrained('projects')
                  ->onDelete('cascade');

            $table->integer('version_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status_version', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');

            $table->foreignId('submitted_by')
                ->constrained('users')
                ->OnDelete('cascade');

            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_versions');
    }
};
