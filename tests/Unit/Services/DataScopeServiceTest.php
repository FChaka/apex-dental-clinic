<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Tenant\StaffMember;
use App\Services\DataScopeService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->service = app(DataScopeService::class);
});

it('treats practice admin as calendar and reports wide', function () {
    $admin = StaffMember::make([
        'id' => 1,
        'clinic_access_level' => 'admin',
        'role' => 'Dentist',
    ]);

    expect($this->service->isPracticeAdmin($admin))->toBeTrue()
        ->and($this->service->isCalendarWide($admin))->toBeTrue()
        ->and($this->service->isReportsWide($admin))->toBeTrue();
});

it('treats receptionist staff as calendar and reports wide', function () {
    $r = StaffMember::make([
        'id' => 2,
        'clinic_access_level' => 'staff',
        'role' => 'Receptionist',
    ]);

    expect($this->service->isCalendarWide($r))->toBeTrue()
        ->and($this->service->isReportsWide($r))->toBeTrue()
        ->and($this->service->isPracticeAdmin($r))->toBeFalse();
});

it('treats dental nurse as calendar wide but not reports wide', function () {
    $n = StaffMember::make([
        'id' => 3,
        'clinic_access_level' => 'staff',
        'role' => 'Dental Nurse',
    ]);

    expect($this->service->isCalendarWide($n))->toBeTrue()
        ->and($this->service->isReportsWide($n))->toBeFalse();
});

it('treats dentist staff as neither wide scope', function () {
    $d = StaffMember::make([
        'id' => 4,
        'clinic_access_level' => 'staff',
        'role' => 'Dentist',
    ]);

    expect($this->service->isCalendarWide($d))->toBeFalse()
        ->and($this->service->isReportsWide($d))->toBeFalse();
});

it('resolves drill-down for reports wide gate', function () {
    $admin = StaffMember::make(['id' => 1, 'clinic_access_level' => 'admin', 'role' => 'Dentist']);

    expect($this->service->resolveTargetDentistId($admin, null, 'reports_wide'))->toBeNull()
        ->and($this->service->resolveTargetDentistId($admin, 7, 'reports_wide'))->toBe(7);
});

it('forces own dentist scope for reports gate when not wide', function () {
    $d = StaffMember::make(['clinic_access_level' => 'staff', 'role' => 'Dentist']);
    $d->id = 11;

    expect($this->service->resolveTargetDentistId($d, null, 'reports_wide'))->toBe(11)
        ->and($this->service->resolveTargetDentistId($d, 11, 'reports_wide'))->toBe(11);
});

it('throws when non wide user requests foreign dentist id for reports_wide', function () {
    $d = StaffMember::make(['clinic_access_level' => 'staff', 'role' => 'Dentist']);
    $d->id = 11;

    expect(fn () => $this->service->resolveTargetDentistId($d, 99, 'reports_wide'))
        ->toThrow(AuthorizationException::class);
});

it('respects calendar_wide gate for nurse', function () {
    $n = StaffMember::make(['id' => 3, 'clinic_access_level' => 'staff', 'role' => 'Dental Nurse']);

    expect($this->service->resolveTargetDentistId($n, null, 'calendar_wide'))->toBeNull();
});
