<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClinicSchedule;
use App\Models\Tenant\StaffMember;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ClinicScheduleController extends Controller
{
    public function show(): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $rows = ClinicSchedule::query()
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (ClinicSchedule $row) => $this->serializeScheduleRow($row))
            ->values()
            ->all();

        return JsonApiResponse::success($rows, 'OK');
    }

    public function update(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->isClinicAdmin($staff)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'schedule' => ['required', 'array', 'size:7'],
            'schedule.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedule.*.is_open' => ['required', 'boolean'],
            'schedule.*.start_hour' => ['required', 'integer', 'between:0,23'],
            'schedule.*.end_hour' => ['required', 'integer', 'between:0,23'],
        ]);

        /** @var array<int, array{day_of_week:int,is_open:bool,start_hour:int,end_hour:int}> $schedule */
        $schedule = $validated['schedule'];

        foreach ($schedule as $row) {
            if ($row['is_open'] && $row['end_hour'] <= $row['start_hour']) {
                return response()->json([
                    'message' => 'The schedule end_hour must be greater than start_hour for open days.',
                ], 422);
            }
        }

        DB::transaction(function () use ($schedule) {
            foreach ($schedule as $row) {
                ClinicSchedule::query()->updateOrCreate(
                    ['day_of_week' => $row['day_of_week']],
                    [
                        'day_of_week' => $row['day_of_week'],
                        'is_open' => $row['is_open'],
                        'start_hour' => $this->hourToTimeString($row['start_hour']),
                        'end_hour' => $this->hourToTimeString($row['end_hour']),
                    ]
                );
            }
        });

        $rows = ClinicSchedule::query()
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (ClinicSchedule $row) => $this->serializeScheduleRow($row))
            ->values()
            ->all();

        return JsonApiResponse::success($rows, 'OK');
    }

    private function clinicStaff(): StaffMember|JsonResponse
    {
        $staff = auth('clinic_session')->user();
        if (! $staff instanceof StaffMember) {
            return JsonApiResponse::unauthorized();
        }

        return $staff;
    }

    private function isClinicAdmin(StaffMember $staff): bool
    {
        return in_array($staff->clinic_access_level, ['super_admin', 'admin'], true);
    }

    private function hourToTimeString(int $hour): string
    {
        return str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00:00';
    }

    private function timeStringToHour(?string $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $hour = (int) substr($value, 0, 2);

        return $hour >= 0 && $hour <= 23 ? $hour : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeScheduleRow(ClinicSchedule $row): array
    {
        return [
            'day_of_week' => (int) $row->day_of_week,
            'is_open' => (bool) $row->is_open,
            'start_hour' => $this->timeStringToHour($row->start_hour),
            'end_hour' => $this->timeStringToHour($row->end_hour),
        ];
    }
}
