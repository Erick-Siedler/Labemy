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
        Schema::create('sub_folders', function (Blueprint $table) {
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

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('order_index')->default(1);
            $table->unsignedBigInteger('current_version')->default(0);

            $table->unique(['project_id', 'slug']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_folders');
    }
};
