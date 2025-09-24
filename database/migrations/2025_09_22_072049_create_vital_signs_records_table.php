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
        Schema::create('vital_signs_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vital_sign_type_id')->constrained()->onDelete('cascade');
            $table->decimal('value_primary', 10, 2);
            $table->decimal('value_secondary', 10, 2)->nullable();
            $table->string('unit');
            $table->datetime('measured_at');
            $table->text('notes')->nullable();
            $table->enum('measurement_method', ['manual', 'device', 'estimated'])->default('manual');
            $table->string('device_name')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'vital_sign_type_id', 'measured_at']);
            $table->index('measured_at');
            $table->index('is_flagged');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs_records');
    }
};
