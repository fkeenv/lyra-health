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
        // Add optimized indexes for trend analysis queries
        Schema::table('vital_signs_records', function (Blueprint $table) {
            // Composite index for trend queries (most common query pattern)
            // Covers: WHERE user_id = ? AND vital_sign_type_id = ? AND measured_at BETWEEN ? AND ? ORDER BY measured_at
            $table->index(['user_id', 'vital_sign_type_id', 'measured_at', 'value_primary'], 'idx_trend_analysis');

            // Index for flagged readings queries (health alerts)
            // Covers: WHERE user_id = ? AND is_flagged = true AND measured_at >= ?
            $table->index(['user_id', 'is_flagged', 'measured_at'], 'idx_flagged_readings');

            // Index for recent readings queries (dashboard and recent activity)
            // Covers: WHERE user_id = ? ORDER BY measured_at DESC LIMIT ?
            $table->index(['user_id', 'measured_at'], 'idx_recent_readings');

            // Index for vital sign type specific queries with time range
            // Covers: WHERE vital_sign_type_id = ? AND measured_at BETWEEN ? AND ?
            $table->index(['vital_sign_type_id', 'measured_at', 'value_primary'], 'idx_type_time_value');

            // Partial index for active (non-flagged) readings for performance comparisons
            // This helps with queries that filter out flagged readings for baseline calculations
            if (config('database.default') === 'pgsql') {
                // PostgreSQL supports partial indexes
                DB::statement('CREATE INDEX CONCURRENTLY idx_active_readings_partial ON vital_signs_records (user_id, vital_sign_type_id, measured_at, value_primary) WHERE is_flagged = false');
            } else {
                // For other databases, use regular index
                $table->index(['is_flagged', 'user_id', 'vital_sign_type_id', 'measured_at'], 'idx_active_readings');
            }
        });

        // Optimize recommendations table for quick lookups
        Schema::table('recommendations', function (Blueprint $table) {
            // Index for finding active recommendations for a user
            // Covers: WHERE user_id = ? AND is_active = true ORDER BY priority, created_at DESC
            $table->index(['user_id', 'is_active', 'priority', 'created_at'], 'idx_active_recommendations');

            // Index for deduplication queries
            // Covers: WHERE user_id = ? AND recommendation_type = ? AND created_at >= ?
            $table->index(['user_id', 'recommendation_type', 'created_at'], 'idx_deduplication');

            // Index for cleanup queries (expired recommendations)
            // Covers: WHERE expires_at <= ? OR (is_active = true AND created_at <= ?)
            $table->index(['expires_at', 'is_active', 'created_at'], 'idx_cleanup');
        });

        // Optimize data access logs table for audit queries
        Schema::table('data_access_logs', function (Blueprint $table) {
            // Index for medical professional access history
            // Covers: WHERE medical_professional_id = ? AND accessed_at BETWEEN ? AND ?
            $table->index(['medical_professional_id', 'accessed_at'], 'idx_provider_access_history');

            // Index for patient access audit
            // Covers: WHERE patient_id = ? ORDER BY accessed_at DESC
            $table->index(['patient_id', 'accessed_at'], 'idx_patient_audit');

            // Composite index for consent-based access queries
            // Covers: WHERE medical_professional_id = ? AND patient_id = ? AND access_type = ?
            $table->index(['medical_professional_id', 'patient_id', 'access_type'], 'idx_consent_access');
        });

        // Optimize consent table for quick authorization checks
        Schema::table('patient_provider_consents', function (Blueprint $table) {
            // Index for active consent lookups (most critical for performance)
            // Covers: WHERE patient_id = ? AND medical_professional_id = ? AND status = 'active'
            $table->index(['patient_id', 'medical_professional_id', 'status'], 'idx_active_consent_lookup');

            // Index for provider patient lists
            // Covers: WHERE medical_professional_id = ? AND status IN ('active', 'pending') ORDER BY granted_at DESC
            $table->index(['medical_professional_id', 'status', 'granted_at'], 'idx_provider_patients');

            // Index for expiring consents (cleanup and notifications)
            // Covers: WHERE status = 'active' AND expires_at BETWEEN ? AND ?
            $table->index(['status', 'expires_at'], 'idx_expiring_consents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vital_signs_records', function (Blueprint $table) {
            $table->dropIndex('idx_trend_analysis');
            $table->dropIndex('idx_flagged_readings');
            $table->dropIndex('idx_recent_readings');
            $table->dropIndex('idx_type_time_value');

            if (config('database.default') === 'pgsql') {
                DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_active_readings_partial');
            } else {
                $table->dropIndex('idx_active_readings');
            }
        });

        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropIndex('idx_active_recommendations');
            $table->dropIndex('idx_deduplication');
            $table->dropIndex('idx_cleanup');
        });

        Schema::table('data_access_logs', function (Blueprint $table) {
            $table->dropIndex('idx_provider_access_history');
            $table->dropIndex('idx_patient_audit');
            $table->dropIndex('idx_consent_access');
        });

        Schema::table('patient_provider_consents', function (Blueprint $table) {
            $table->dropIndex('idx_active_consent_lookup');
            $table->dropIndex('idx_provider_patients');
            $table->dropIndex('idx_expiring_consents');
        });
    }
};
