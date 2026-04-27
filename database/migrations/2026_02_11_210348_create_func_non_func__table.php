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
        Schema::create('func_non_func', function (Blueprint $table) {
            $table->id();
            $table->foreignId('func_req_id')
                ->constrained('func_reqs')
                ->onDelete('cascade');
            $table->foreignId('non_func_req_id')
                ->constrained('non_func_reqs')
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['func_req_id', 'non_func_req_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('func_non_func');
    }
};
