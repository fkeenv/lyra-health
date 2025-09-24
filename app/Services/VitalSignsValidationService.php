<?php

namespace App\Services;

use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use Carbon\Carbon;

class VitalSignsValidationService
{
    /**
     * Validate a vital signs reading and determine if it should be flagged.
     */
    public function validateReading(
        VitalSignType $vitalSignType,
        string $primaryValue,
        ?string $secondaryValue = null,
        ?array $userContext = null
    ): array {
        $primary = (float) $primaryValue;
        $secondary = $secondaryValue ? (float) $secondaryValue : null;

        $result = [
            'is_normal' => true,
            'is_flagged' => false,
            'warning_level' => 'normal',
            'flag_reason' => null,
            'recommendations' => [],
        ];

        // Check primary value against normal ranges
        if ($primary < $vitalSignType->normal_range_min || $primary > $vitalSignType->normal_range_max) {
            $result['is_normal'] = false;

            // Check if it's in warning range or critical
            if ($primary < $vitalSignType->warning_range_min || $primary > $vitalSignType->warning_range_max) {
                $result['is_flagged'] = true;
                $result['warning_level'] = 'critical';
                $result['flag_reason'] = 'Critical reading outside safe range';
            } else {
                $result['warning_level'] = 'warning';
                $result['flag_reason'] = 'Reading outside normal range';
            }
        }

        // Special validation for blood pressure (dual values)
        if ($vitalSignType->name === 'blood_pressure' && $secondary !== null) {
            // Check diastolic pressure
            if ($secondary < 60 || $secondary > 100) {
                $result['is_normal'] = false;
                if ($secondary < 40 || $secondary > 120) {
                    $result['is_flagged'] = true;
                    $result['warning_level'] = 'critical';
                    $result['flag_reason'] = 'Critical diastolic pressure';
                }
            }

            // Check pulse pressure (difference between systolic and diastolic)
            $pulsePressure = $primary - $secondary;
            if ($pulsePressure < 25 || $pulsePressure > 60) {
                $result['is_normal'] = false;
                if ($result['warning_level'] === 'normal') {
                    $result['warning_level'] = 'warning';
                    $result['flag_reason'] = 'Abnormal pulse pressure';
                }
            }
        }

        // Apply age-based adjustments if user context provided
        if ($userContext && isset($userContext['age'])) {
            $result = $this->applyAgeAdjustments($result, $vitalSignType, $primary, $secondary, $userContext['age']);
        }

        // Apply medication adjustments if provided
        if ($userContext && isset($userContext['medications'])) {
            $result = $this->applyMedicationAdjustments($result, $vitalSignType, $primary, $userContext['medications']);
        }

        return $result;
    }

    /**
     * Check for physiological impossibilities.
     */
    public function checkPhysiologicalLimits(VitalSignType $vitalSignType, string $primaryValue, ?string $secondaryValue = null): bool
    {
        $primary = (float) $primaryValue;
        $secondary = $secondaryValue ? (float) $secondaryValue : null;

        return match ($vitalSignType->name) {
            'blood_pressure' => $this->validateBloodPressureLimits($primary, $secondary),
            'heart_rate' => $primary >= 20 && $primary <= 250,
            'oxygen_saturation' => $primary >= 50 && $primary <= 100,
            'weight' => $primary >= 20 && $primary <= 300,
            'blood_glucose' => $primary >= 20 && $primary <= 600,
            'body_temperature' => $primary >= 30 && $primary <= 45,
            default => true,
        };
    }

    /**
     * Validate blood pressure physiological limits.
     */
    private function validateBloodPressureLimits(float $systolic, ?float $diastolic): bool
    {
        // Systolic must be reasonable
        if ($systolic < 50 || $systolic > 300) {
            return false;
        }

        // Diastolic must be reasonable if provided
        if ($diastolic !== null) {
            if ($diastolic < 30 || $diastolic > 200) {
                return false;
            }

            // Systolic should be higher than diastolic
            if ($systolic <= $diastolic) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply age-based adjustments to validation results.
     */
    private function applyAgeAdjustments(array $result, VitalSignType $vitalSignType, float $primary, ?float $secondary, int $age): array
    {
        if ($vitalSignType->name === 'blood_pressure') {
            // Older adults typically have higher acceptable ranges
            if ($age >= 65) {
                // More lenient systolic pressure for elderly
                if ($primary <= 150 && $result['warning_level'] === 'warning') {
                    $result['warning_level'] = 'normal';
                    $result['is_normal'] = true;
                    $result['flag_reason'] = null;
                }
            }
        }

        if ($vitalSignType->name === 'heart_rate') {
            // Children and elderly have different normal ranges
            if ($age < 18) {
                // Higher heart rates are normal for children
                if ($primary <= 110 && $result['warning_level'] !== 'normal') {
                    $result['warning_level'] = 'normal';
                    $result['is_normal'] = true;
                    $result['flag_reason'] = null;
                }
            }
        }

        return $result;
    }

    /**
     * Apply medication-based adjustments to validation results.
     */
    private function applyMedicationAdjustments(array $result, VitalSignType $vitalSignType, float $primary, array $medications): array
    {
        $betaBlockers = ['metoprolol', 'atenolol', 'carvedilol', 'propranolol'];
        $bpMedications = ['lisinopril', 'amlodipine', 'losartan', 'hydrochlorothiazide'];

        if ($vitalSignType->name === 'heart_rate') {
            // Beta blockers lower heart rate - adjust expectations
            foreach ($medications as $medication) {
                if (in_array(strtolower($medication), $betaBlockers)) {
                    if ($primary >= 50 && $result['warning_level'] === 'warning') {
                        $result['warning_level'] = 'normal';
                        $result['is_normal'] = true;
                        $result['flag_reason'] = null;
                        $result['recommendations'][] = 'Heart rate adjusted for beta blocker medication';
                    }
                    break;
                }
            }
        }

        if ($vitalSignType->name === 'blood_pressure') {
            // BP medications lower pressure - adjust expectations
            foreach ($medications as $medication) {
                if (in_array(strtolower($medication), $bpMedications)) {
                    if ($primary >= 110 && $result['warning_level'] === 'warning') {
                        $result['warning_level'] = 'normal';
                        $result['is_normal'] = true;
                        $result['flag_reason'] = null;
                        $result['recommendations'][] = 'Blood pressure adjusted for hypertension medication';
                    }
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Validate a sequence of readings for trends.
     */
    public function validateReadingSequence(array $readings, VitalSignType $vitalSignType, int $timeWindowDays = 7): array
    {
        if (count($readings) < 3) {
            return ['is_valid' => true, 'warnings' => []];
        }

        $values = array_map(fn($reading) => (float) $reading['value_primary'], $readings);
        $timestamps = array_map(fn($reading) => Carbon::parse($reading['measured_at']), $readings);

        $result = ['is_valid' => true, 'warnings' => []];

        // Check for rapid changes
        $rapidChanges = $this->detectRapidChanges($values, $timestamps, $vitalSignType);
        if ($rapidChanges) {
            $result['warnings'][] = 'Rapid changes detected in recent readings';
        }

        // Check for measurement consistency
        $inconsistencies = $this->detectMeasurementInconsistencies($values, $vitalSignType);
        if ($inconsistencies) {
            $result['warnings'][] = 'Inconsistent measurement patterns detected';
        }

        return $result;
    }

    /**
     * Detect rapid changes in vital signs.
     */
    private function detectRapidChanges(array $values, array $timestamps, VitalSignType $vitalSignType): bool
    {
        if (count($values) < 2) {
            return false;
        }

        // Define thresholds for rapid changes by vital sign type
        $changeThresholds = [
            'blood_pressure' => 20, // 20 mmHg change in short time
            'heart_rate' => 15,     // 15 BPM change
            'weight' => 2,          // 2 kg change in a day
            'blood_glucose' => 50,  // 50 mg/dL change
        ];

        $threshold = $changeThresholds[$vitalSignType->name] ?? 10;

        for ($i = 1; $i < count($values); $i++) {
            $change = abs($values[$i] - $values[$i - 1]);
            $timeDiff = $timestamps[$i]->diffInHours($timestamps[$i - 1]);

            // Check for significant change in short time
            if ($change > $threshold && $timeDiff < 24) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect measurement inconsistencies.
     */
    private function detectMeasurementInconsistencies(array $values, VitalSignType $vitalSignType): bool
    {
        if (count($values) < 5) {
            return false;
        }

        // Calculate coefficient of variation
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        $stdDev = sqrt($variance);
        $coefficientOfVariation = $mean > 0 ? ($stdDev / $mean) : 0;

        // Thresholds for coefficient of variation by vital sign type
        $cvThresholds = [
            'blood_pressure' => 0.15, // 15% variation is concerning
            'heart_rate' => 0.20,     // 20% variation
            'weight' => 0.05,         // 5% variation in weight
            'blood_glucose' => 0.25,  // 25% variation (more variable)
        ];

        $threshold = $cvThresholds[$vitalSignType->name] ?? 0.20;

        return $coefficientOfVariation > $threshold;
    }
}