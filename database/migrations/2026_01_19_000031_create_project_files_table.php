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
        Schema::create('project_files', function (Blueprint $table) {
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

            $table->foreignId('project_versions_id')
                  ->constrained('project_versions')
                  ->onDelete('cascade');

            $table->foreignId('uploaded_by')
                    ->constrained('users')
                    ->onDelete('cascade');
            
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('extension');
            $table->string('mime_type');
            $table->bigInteger('size');
            $table->enum('type', ['document', 'image', 'source_code', 'compressed', 'other']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_files');
    }
};
