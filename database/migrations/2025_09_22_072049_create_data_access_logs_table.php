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
        Schema::create('data_access_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('medical_professional_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->datetime('accessed_at');
            $table->enum('access_type', ['view', 'export', 'print']);
            $table->json('data_scope');
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->integer('session_duration')->nullable()->comment('Duration in seconds');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'accessed_at']);
            $table->index(['medical_professional_id', 'accessed_at']);
            $table->index('accessed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_access_logs');
    }
};
