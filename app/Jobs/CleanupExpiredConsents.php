<?php

namespace App\Jobs;

use App\Models\DataAccessLog;
use App\Models\PatientProviderConsent;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredConsents implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes

    public array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'cleanup_types' => ['expired', 'long_revoked', 'inactive_access'],
            'expired_grace_days' => 7, // Days after expiration before marking as expired
            'revoked_cleanup_days' => 90, // Days to keep revoked consents before archiving
            'inactive_cleanup_days' => 365, // Days with no access before considering inactive
            'send_notifications' => true,
            'dry_run' => false,
        ], $options);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting consent cleanup job', [
            'options' => $this->options,
        ]);

        $startTime = microtime(true);
        $cleanupStats = [
            'expired_processed' => 0,
            'long_revoked_archived' => 0,
            'inactive_flagged' => 0,
            'notifications_sent' => 0,
            'errors' => 0,
        ];

        try {
            // Cleanup expired consents
            if (in_array('expired', $this->options['cleanup_types'])) {
                $cleanupStats['expired_processed'] = $this->processExpiredConsents();
            }

            // Archive long-revoked consents
            if (in_array('long_revoked', $this->options['cleanup_types'])) {
                $cleanupStats['long_revoked_archived'] = $this->archiveLongRevokedConsents();
            }

            // Flag inactive access consents
            if (in_array('inactive_access', $this->options['cleanup_types'])) {
                $cleanupStats['inactive_flagged'] = $this->flagInactiveConsents();
            }

            // Send notification summaries
            if ($this->options['send_notifications']) {
                $cleanupStats['notifications_sent'] = $this->sendNotificationSummaries();
            }

            $executionTime = round(microtime(true) - $startTime, 2);

            Log::info('Consent cleanup completed', [
                'stats' => $cleanupStats,
                'execution_time_seconds' => $executionTime,
                'dry_run' => $this->options['dry_run'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error during consent cleanup', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'stats' => $cleanupStats,
            ]);

            throw $e;
        }
    }

    /**
     * Process expired consents.
     */
    private function processExpiredConsents(): int
    {
        $gracePeriodDate = Carbon::now()->subDays($this->options['expired_grace_days']);

        $expiredConsents = PatientProviderConsent::where('status', 'active')
            ->where('expires_at', '<', $gracePeriodDate)
            ->whereNotNull('expires_at')
            ->with(['patient', 'medicalProfessional'])
            ->get();

        $processedCount = 0;

        foreach ($expiredConsents as $consent) {
            try {
                if ($this->options['dry_run']) {
                    Log::info('DRY RUN - Would expire consent', [
                        'consent_id' => $consent->id,
                        'patient_id' => $consent->patient_id,
                        'medical_professional_id' => $consent->medical_professional_id,
                        'expired_at' => $consent->expires_at->toISOString(),
                    ]);
                } else {
                    // Update consent status
                    $consent->update([
                        'status' => 'expired',
                        'updated_at' => now(),
                    ]);

                    // Log the expiration
                    DataAccessLog::create([
                        'medical_professional_id' => $consent->medical_professional_id,
                        'patient_id' => $consent->patient_id,
                        'access_type' => 'consent_expired',
                        'data_accessed' => ['consent_status'],
                        'accessed_at' => now(),
                        'notes' => 'Consent automatically expired by cleanup job',
                        'ip_address' => '127.0.0.1', // System IP
                        'user_agent' => 'ConsentCleanupJob',
                    ]);

                    // Send notification to patient about expiration
                    $this->sendConsentExpirationNotification($consent);
                }

                $processedCount++;

            } catch (\Exception $e) {
                Log::error('Failed to process expired consent', [
                    'consent_id' => $consent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processedCount;
    }

    /**
     * Archive long-revoked consents.
     */
    private function archiveLongRevokedConsents(): int
    {
        $archiveDate = Carbon::now()->subDays($this->options['revoked_cleanup_days']);

        $longRevokedConsents = PatientProviderConsent::where('status', 'revoked')
            ->where('revoked_at', '<', $archiveDate)
            ->whereNotNull('revoked_at')
            ->with(['patient', 'medicalProfessional'])
            ->get();

        $archivedCount = 0;

        foreach ($longRevokedConsents as $consent) {
            try {
                if ($this->options['dry_run']) {
                    Log::info('DRY RUN - Would archive revoked consent', [
                        'consent_id' => $consent->id,
                        'patient_id' => $consent->patient_id,
                        'medical_professional_id' => $consent->medical_professional_id,
                        'revoked_at' => $consent->revoked_at->toISOString(),
                    ]);
                } else {
                    // Update consent status to archived
                    $consent->update([
                        'status' => 'archived',
                        'updated_at' => now(),
                    ]);

                    // Create archive log entry
                    DataAccessLog::create([
                        'medical_professional_id' => $consent->medical_professional_id,
                        'patient_id' => $consent->patient_id,
                        'access_type' => 'consent_archived',
                        'data_accessed' => ['consent_status'],
                        'accessed_at' => now(),
                        'notes' => "Consent archived after {$this->options['revoked_cleanup_days']} days in revoked status",
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'ConsentCleanupJob',
                    ]);
                }

                $archivedCount++;

            } catch (\Exception $e) {
                Log::error('Failed to archive revoked consent', [
                    'consent_id' => $consent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $archivedCount;
    }

    /**
     * Flag consents with no recent access.
     */
    private function flagInactiveConsents(): int
    {
        $inactiveDate = Carbon::now()->subDays($this->options['inactive_cleanup_days']);

        // Find active consents that haven't been accessed recently
        $potentiallyInactiveConsents = PatientProviderConsent::where('status', 'active')
            ->where('granted_at', '<', $inactiveDate)
            ->whereDoesntHave('dataAccessLogs', function ($query) use ($inactiveDate) {
                $query->where('accessed_at', '>', $inactiveDate);
            })
            ->with(['patient', 'medicalProfessional'])
            ->get();

        $flaggedCount = 0;

        foreach ($potentiallyInactiveConsents as $consent) {
            try {
                // Get the last access date if any
                $lastAccess = DataAccessLog::where('medical_professional_id', $consent->medical_professional_id)
                    ->where('patient_id', $consent->patient_id)
                    ->orderBy('accessed_at', 'desc')
                    ->first();

                $lastAccessDate = $lastAccess ? $lastAccess->accessed_at : $consent->granted_at;

                if ($this->options['dry_run']) {
                    Log::info('DRY RUN - Would flag inactive consent', [
                        'consent_id' => $consent->id,
                        'patient_id' => $consent->patient_id,
                        'medical_professional_id' => $consent->medical_professional_id,
                        'last_access' => $lastAccessDate->toISOString(),
                        'days_inactive' => $lastAccessDate->diffInDays(Carbon::now()),
                    ]);
                } else {
                    // Add inactive flag to consent data
                    $data = is_string($consent->data) ? json_decode($consent->data, true) : ($consent->data ?? []);
                    $data['flags'] = array_merge($data['flags'] ?? [], ['inactive_access']);
                    $data['inactive_flagged_at'] = now()->toISOString();
                    $data['last_access_date'] = $lastAccessDate->toISOString();

                    $consent->update([
                        'data' => $data,
                        'updated_at' => now(),
                    ]);

                    // Send notification to patient about inactive consent
                    $this->sendInactiveConsentNotification($consent, $lastAccessDate);
                }

                $flaggedCount++;

            } catch (\Exception $e) {
                Log::error('Failed to flag inactive consent', [
                    'consent_id' => $consent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $flaggedCount;
    }

    /**
     * Send notification summaries to relevant parties.
     */
    private function sendNotificationSummaries(): int
    {
        // This would integrate with email notifications when implemented
        // For now, we'll just log the notifications that would be sent

        $notificationCount = 0;

        // Get counts of various consent states for summary
        $summaryData = [
            'total_active' => PatientProviderConsent::where('status', 'active')->count(),
            'total_expired' => PatientProviderConsent::where('status', 'expired')->count(),
            'total_revoked' => PatientProviderConsent::where('status', 'revoked')->count(),
            'total_archived' => PatientProviderConsent::where('status', 'archived')->count(),
            'expiring_soon' => PatientProviderConsent::where('status', 'active')
                ->where('expires_at', '>', now())
                ->where('expires_at', '<=', now()->addDays(30))
                ->count(),
        ];

        Log::info('Consent status summary', $summaryData);

        // In a real implementation, this would send emails to:
        // 1. System administrators with cleanup summary
        // 2. Patients with expiring consents (30-day warning)
        // 3. Medical professionals with consent status changes

        return $notificationCount;
    }

    /**
     * Send consent expiration notification.
     */
    private function sendConsentExpirationNotification(PatientProviderConsent $consent): void
    {
        // In a real implementation, this would send an email/SMS to the patient
        Log::info('Consent expiration notification', [
            'consent_id' => $consent->id,
            'patient_email' => $consent->patient->email ?? 'N/A',
            'medical_professional' => $consent->medicalProfessional->name ?? 'N/A',
            'expired_at' => $consent->expires_at->toISOString(),
        ]);
    }

    /**
     * Send inactive consent notification.
     */
    private function sendInactiveConsentNotification(PatientProviderConsent $consent, Carbon $lastAccessDate): void
    {
        // In a real implementation, this would send an email to the patient
        Log::info('Inactive consent notification', [
            'consent_id' => $consent->id,
            'patient_email' => $consent->patient->email ?? 'N/A',
            'medical_professional' => $consent->medicalProfessional->name ?? 'N/A',
            'last_access' => $lastAccessDate->toISOString(),
            'days_inactive' => $lastAccessDate->diffInDays(Carbon::now()),
        ]);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CleanupExpiredConsents job failed', [
            'options' => $this->options,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
