<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientAnamnesis;
use App\Models\Tenant\PatientMedicalHistory;
use App\Models\Tenant\StaffMember;
use Carbon\CarbonInterface;

/**
 * @phpstan-type PatientMedicalHistoryArray array{allergies: array<mixed>|null, conditions: array<mixed>|null, notes: string|null}
 * @phpstan-type PatientAnamnesisArray array{chief_complaint: string|null, present_illness: string|null, current_medications: string|null, previous_surgeries: string|null, family_history: string|null, dental_history: string|null, other: string|null}
 */
final class PatientArraySerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function staffSubset(?StaffMember $dentist): ?array
    {
        if ($dentist === null) {
            return null;
        }

        return [
            'id' => $dentist->id,
            'name' => $dentist->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function patient(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'name' => $patient->name,
            'surname' => $patient->surname,
            'fathers_name' => $patient->fathers_name,
            'birthday' => self::formatDate($patient->birthday),
            'gender' => $patient->gender,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'address' => $patient->address,
            'city' => $patient->city,
            'personal_number' => $patient->personal_number,
            'blood_type' => $patient->blood_type,
            'avatar_path' => $patient->avatar_path,
            'general_notes' => $patient->general_notes,
            'last_visit' => self::formatDate($patient->last_visit),
            'status' => $patient->status,
            'medical_alert' => $patient->medical_alert,
            'created_at' => $patient->created_at?->toIso8601String(),
            'updated_at' => $patient->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function patientListItem(Patient $patient): array
    {
        return self::patient($patient);
    }

    /**
     * @return PatientMedicalHistoryArray
     */
    public static function medicalHistory(?PatientMedicalHistory $row): array
    {
        if ($row === null) {
            return [
                'allergies' => [],
                'conditions' => [],
                'notes' => null,
            ];
        }

        return [
            'allergies' => $row->allergies ?? [],
            'conditions' => $row->conditions ?? [],
            'notes' => $row->notes,
        ];
    }

    /**
     * @return PatientAnamnesisArray
     */
    public static function anamnesis(?PatientAnamnesis $row): array
    {
        if ($row === null) {
            return [
                'chief_complaint' => null,
                'present_illness' => null,
                'current_medications' => null,
                'previous_surgeries' => null,
                'family_history' => null,
                'dental_history' => null,
                'other' => null,
            ];
        }

        return [
            'chief_complaint' => $row->chief_complaint,
            'present_illness' => $row->present_illness,
            'current_medications' => $row->current_medications,
            'previous_surgeries' => $row->previous_surgeries,
            'family_history' => $row->family_history,
            'dental_history' => $row->dental_history,
            'other' => $row->other,
        ];
    }

    private static function formatDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return null;
    }
}
