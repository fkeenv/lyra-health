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
        Schema::create('patient_provider_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('medical_professional_id')->constrained()->onDelete('cascade');
            $table->datetime('consent_given_at');
            $table->datetime('consent_expires_at')->nullable();
            $table->enum('access_level', ['read_only', 'full_access'])->default('read_only');
            $table->boolean('granted_by_user')->default(true);
            $table->boolean('emergency_access')->default(false);
            $table->boolean('is_active')->default(true);
            $table->datetime('revoked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'medical_professional_id'], 'unique_active_consent')
                ->where('is_active', true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_provider_consents');
    }
};
