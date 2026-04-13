<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\TeethChartData;
use App\Models\Tenant\TeethChartSurface;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PatientTeethChartController extends Controller
{
    use InteractsWithClinicPatient;

    private const PROCEDURES = ['Filling', 'Crown', 'Extraction', 'Root Canal', 'Implant'];

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function show(Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        return JsonApiResponse::success([
            'procedures' => [
                'initial_exam' => $this->serializeProcedureLayer($patient, true),
                'current' => $this->serializeProcedureLayer($patient, false),
            ],
            'surfaces' => [
                'initial_exam' => $this->serializeSurfaceLayer($patient, true),
                'current' => $this->serializeSurfaceLayer($patient, false),
            ],
        ], 'OK');
    }

    public function update(Request $request, Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $validated = $request->validate([
            'is_initial_exam' => ['required', 'boolean'],
            'procedures' => ['present', 'array'],
            'procedures.*.tooth_number' => ['required', 'string', 'max:10'],
            'procedures.*.procedure' => ['nullable', Rule::in(self::PROCEDURES)],
            'procedures.*.notes' => ['nullable', 'string'],
            'surfaces' => ['present', 'array'],
            'surfaces.*.tooth_number' => ['required', 'string', 'max:10'],
            'surfaces.*.surface_key' => ['required', 'string', 'max:20'],
            'surfaces.*.values' => ['required', 'array'],
        ]);

        $isInitialExam = $validated['is_initial_exam'];

        DB::transaction(function () use ($patient, $validated, $isInitialExam) {
            TeethChartData::query()
                ->where('patient_id', $patient->id)
                ->where('is_initial_exam', $isInitialExam)
                ->delete();

            TeethChartSurface::query()
                ->where('patient_id', $patient->id)
                ->where('is_initial_exam', $isInitialExam)
                ->delete();

            $now = now();
            $procedureRows = [];
            foreach ($validated['procedures'] as $row) {
                $procedureRows[] = [
                    'patient_id' => $patient->id,
                    'tooth_number' => $row['tooth_number'],
                    'procedure' => $row['procedure'] ?? null,
                    'is_initial_exam' => $isInitialExam,
                    'notes' => $row['notes'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($procedureRows !== []) {
                TeethChartData::query()->insert($procedureRows);
            }

            $surfaceRows = [];
            foreach ($validated['surfaces'] as $row) {
                $surfaceRows[] = [
                    'patient_id' => $patient->id,
                    'tooth_number' => $row['tooth_number'],
                    'surface_key' => $row['surface_key'],
                    'values' => json_encode($row['values']),
                    'is_initial_exam' => $isInitialExam,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($surfaceRows !== []) {
                TeethChartSurface::query()->insert($surfaceRows);
            }
        });

        return $this->show($patient);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeProcedureLayer(Patient $patient, bool $isInitialExam): array
    {
        return TeethChartData::query()
            ->where('patient_id', $patient->id)
            ->where('is_initial_exam', $isInitialExam)
            ->orderBy('id')
            ->get()
            ->map(fn (TeethChartData $r) => $this->serializeProcedure($r))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeSurfaceLayer(Patient $patient, bool $isInitialExam): array
    {
        return TeethChartSurface::query()
            ->where('patient_id', $patient->id)
            ->where('is_initial_exam', $isInitialExam)
            ->orderBy('id')
            ->get()
            ->map(fn (TeethChartSurface $r) => $this->serializeSurface($r))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProcedure(TeethChartData $r): array
    {
        return [
            'id' => $r->id,
            'tooth_number' => $r->tooth_number,
            'procedure' => $r->procedure,
            'is_initial_exam' => $r->is_initial_exam,
            'notes' => $r->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSurface(TeethChartSurface $r): array
    {
        return [
            'id' => $r->id,
            'tooth_number' => $r->tooth_number,
            'surface_key' => $r->surface_key,
            'values' => $r->values ?? [],
            'is_initial_exam' => $r->is_initial_exam,
        ];
    }
}
