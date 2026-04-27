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
        Schema::create('func_reqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->onDelete('cascade');
            $table->foreignId('project_id')
                ->constrained('projects')
                ->onDelete('cascade');
            $table->string('created_by_table', 20);
            $table->unsignedBigInteger('created_by_id');
            $table->string('code', 40);
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('draft');
            $table->longText('acceptance_criteria')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('project_id');
            $table->index(['project_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('func_reqs');
    }
};
