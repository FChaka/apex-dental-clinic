<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Events\NotificationCreated;
use App\Http\Middleware\ResolveTenantFromHeader;
use App\Jobs\SendAppointmentReminders;
use App\Models\Central\Clinic;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\Notification;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\StaffWorkingSchedule;
use App\Services\Notifications\AppointmentReminderService;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->clinic = createTestTenant('notif-test');
    tenancy()->initialize($this->clinic);
});

afterEach(function () {
    if (isset($this->clinic) && $this->clinic instanceof Clinic) {
        dropTenantDatabaseIfExists($this->clinic);
    }
    tenancy()->end();
});

it('broadcasting auth route includes tenant resolution middleware', function (): void {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->uri() === 'broadcasting/auth');

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain(ResolveTenantFromHeader::class);
});

it('notifications index response matches JsonApiResponse envelope', function (): void {
    $staff = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);

    $this->actingAs($staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/notifications'))
        ->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonPath('data', [])
        ->assertJsonPath('unread_count', 0);
});

it('authenticated staff can fetch their own notifications', function (): void {
    $staff = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    app(NotificationService::class)->send($staff->id, NotificationType::LeaveRequestSubmitted, 'Hello', '/staff?tab=leave-requests', null);

    $response = $this->actingAs($staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/notifications'));

    $response->assertOk()
        ->assertJsonPath('message', 'OK')
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(1, 'data');
});

it('returns correct unread_count in response', function (): void {
    $staff = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    $svc = app(NotificationService::class);
    $svc->send($staff->id, NotificationType::ScheduleChanged, 'a', null, null);
    $svc->send($staff->id, NotificationType::UpcomingAppointment, 'b', null, null);
    Notification::query()->where('receiver_staff_id', $staff->id)->first()?->update(['is_read' => true]);

    $this->actingAs($staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/notifications'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1);
});

it('staff cannot see another staff members notifications', function (): void {
    $a = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    $b = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    app(NotificationService::class)->send($b->id, NotificationType::LeaveRequestSubmitted, 'secret', null, null);

    $this->actingAs($a, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/notifications'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('mark one notification as read sets is_read to true', function (): void {
    $staff = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    $n = app(NotificationService::class)->send($staff->id, NotificationType::ScheduleChanged, 'm', null, null);

    $this->actingAs($staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/notifications/{$n->id}/read"))
        ->assertOk()
        ->assertJsonPath('message', 'OK');

    expect((bool) $n->fresh()->is_read)->toBeTrue();
});

it('mark one notification belonging to another staff returns 403', function (): void {
    $a = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    $b = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    $n = app(NotificationService::class)->send($b->id, NotificationType::ScheduleChanged, 'm', null, null);

    $this->actingAs($a, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/notifications/{$n->id}/read"))
        ->assertForbidden();
});

it('read-all marks all notifications for current staff as read', function (): void {
    $staff = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    $svc = app(NotificationService::class);
    $svc->send($staff->id, NotificationType::ScheduleChanged, 'a', null, null);
    $svc->send($staff->id, NotificationType::UpcomingAppointment, 'b', null, null);

    $this->actingAs($staff, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, 'api/notifications/read-all'))
        ->assertOk()
        ->assertJsonPath('message', 'All notifications marked as read.');

    expect(Notification::query()->where('receiver_staff_id', $staff->id)->where('is_read', false)->count())->toBe(0);
});

it('unauthenticated request to notifications returns 401', function (): void {
    $this->withHeaders(clinicStatefulHeaders($this->clinic))
        ->getJson(clinicApiUrl($this->clinic, 'api/notifications'))
        ->assertUnauthorized()
        ->assertJsonPath('data', null)
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('submitting a leave request creates notifications for admins and receptionists', function (): void {
    $super = StaffMember::factory()->create(['clinic_access_level' => 'super_admin', 'status' => 'Active']);
    $admin = StaffMember::factory()->create(['clinic_access_level' => 'admin', 'status' => 'Active']);
    $receptionist = StaffMember::factory()->create([
        'clinic_access_level' => 'admin',
        'role' => 'Receptionist',
        'status' => 'Active',
    ]);
    $requester = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);

    Event::fake([NotificationCreated::class]);

    $this->actingAs($requester, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->postJson(clinicApiUrl($this->clinic, 'api/leave-requests'), [
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
        ])
        ->assertCreated();

    Event::assertDispatchedTimes(NotificationCreated::class, 3);

    $ids = Notification::query()->pluck('receiver_staff_id')->all();
    expect($ids)->toContain($super->id)->toContain($admin->id)->toContain($receptionist->id);

    $paths = Notification::query()->pluck('path')->all();
    expect($paths)->toHaveCount(3)->each->toBe('/staff?tab=leave-requests');
});

it('approving a leave request creates a notification for the original requester', function (): void {
    $admin = StaffMember::factory()->create(['clinic_access_level' => 'admin', 'status' => 'Active']);
    $requester = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    $lr = LeaveRequest::query()->create([
        'staff_id' => $requester->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-02',
        'status' => 'Pending',
        'note' => null,
        'requested_at' => now(),
    ]);

    Event::fake([NotificationCreated::class]);

    $this->actingAs($admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/leave-requests/{$lr->id}"), [
            'status' => 'Approved',
        ])
        ->assertOk();

    Event::assertDispatchedTimes(NotificationCreated::class, 1);
    $n = Notification::query()->where('receiver_staff_id', $requester->id)->first();
    expect($n)->not->toBeNull()
        ->and($n->type)->toBe(NotificationType::LeaveRequestApproved->value);
});

it('rejecting a leave request creates a notification for the original requester', function (): void {
    $admin = StaffMember::factory()->create(['clinic_access_level' => 'admin', 'status' => 'Active']);
    $requester = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);
    $lr = LeaveRequest::query()->create([
        'staff_id' => $requester->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-02',
        'status' => 'Pending',
        'note' => null,
        'requested_at' => now(),
    ]);

    Event::fake([NotificationCreated::class]);

    $this->actingAs($admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/leave-requests/{$lr->id}"), [
            'status' => 'Rejected',
        ])
        ->assertOk();

    Event::assertDispatchedTimes(NotificationCreated::class, 1);
    expect(Notification::query()->where('receiver_staff_id', $requester->id)->value('type'))->toBe(NotificationType::LeaveRequestRejected->value);
});

it('updating a staff schedule creates a notification for the affected staff member', function (): void {
    $admin = StaffMember::factory()->create(['clinic_access_level' => 'admin', 'status' => 'Active']);
    $target = StaffMember::factory()->create(['clinic_access_level' => 'staff', 'status' => 'Active']);

    foreach (range(0, 6) as $day) {
        StaffWorkingSchedule::query()->create([
            'staff_id' => $target->id,
            'day_of_week' => $day,
            'is_open' => $day >= 1 && $day <= 5,
            'start_hour' => 8,
            'end_hour' => 17,
        ]);
    }

    Event::fake([NotificationCreated::class]);

    $days = collect(range(0, 6))->map(fn (int $d) => [
        'day_of_week' => $d,
        'is_open' => $d >= 1 && $d <= 5,
        'start_hour' => $d === 1 ? 9 : 8,
        'end_hour' => 17,
    ])->all();

    $this->actingAs($admin, 'clinic_session')
        ->withHeaders(clinicStatefulHeaders($this->clinic))
        ->putJson(clinicApiUrl($this->clinic, "api/staff/{$target->id}"), [
            'working_schedule' => $days,
        ])
        ->assertOk();

    Event::assertDispatchedTimes(NotificationCreated::class, 1);
    expect(Notification::query()->where('receiver_staff_id', $target->id)->where('type', NotificationType::ScheduleChanged->value)->exists())->toBeTrue();
});

it('creating a notification dispatches NotificationCreated broadcast event', function (): void {
    $staff = StaffMember::factory()->create(['status' => 'Active']);
    Event::fake([NotificationCreated::class]);
    app(NotificationService::class)->send($staff->id, NotificationType::LeaveRequestSubmitted, 'm', '/', null);
    Event::assertDispatched(NotificationCreated::class);
});

describe('dentist appointment reminders', function (): void {
    afterEach(function (): void {
        Mockery::close();
        Carbon::setTestNow();
    });

    it('SendAppointmentReminders notifies dentists for appointments in the 14–16 minute UTC window', function (): void {
        Carbon::setTestNow(Carbon::parse('2026-05-11 12:00:00', 'UTC'));

        $dentist = StaffMember::factory()->create(['status' => 'Active']);
        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => '2026-05-11',
            'time' => '12:15',
            'status' => 'Upcoming',
        ]);

        Event::fake([NotificationCreated::class]);
        SendAppointmentReminders::dispatchSync($this->clinic);

        Event::assertDispatchedTimes(NotificationCreated::class, 1);
    });

    it('SendAppointmentReminders skips appointments when notification_sent is already true', function (): void {
        Carbon::setTestNow(Carbon::parse('2026-05-11 12:00:00', 'UTC'));

        $dentist = StaffMember::factory()->create(['status' => 'Active']);
        $patient = Patient::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => '2026-05-11',
            'time' => '12:15',
            'status' => 'Upcoming',
        ]);

        $appointment->forceFill(['notification_sent' => true])->saveQuietly();

        Event::fake([NotificationCreated::class]);
        SendAppointmentReminders::dispatchSync($this->clinic);

        Event::assertNotDispatched(NotificationCreated::class);
    });

    it('SendAppointmentReminders skips cancelled appointments', function (): void {
        Carbon::setTestNow(Carbon::parse('2026-05-11 12:00:00', 'UTC'));

        $dentist = StaffMember::factory()->create(['status' => 'Active']);
        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => '2026-05-11',
            'time' => '12:15',
            'status' => 'Cancelled',
        ]);

        Event::fake([NotificationCreated::class]);
        SendAppointmentReminders::dispatchSync($this->clinic);

        Event::assertNotDispatched(NotificationCreated::class);
    });

    it('SendAppointmentReminders skips appointments outside the 14–16 minute window', function (): void {
        Carbon::setTestNow(Carbon::parse('2026-05-11 12:00:00', 'UTC'));

        $dentist = StaffMember::factory()->create(['status' => 'Active']);
        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => '2026-05-11',
            'time' => '12:30',
            'status' => 'Upcoming',
        ]);

        Event::fake([NotificationCreated::class]);
        SendAppointmentReminders::dispatchSync($this->clinic);

        Event::assertNotDispatched(NotificationCreated::class);
    });

    it('rescheduling an upcoming appointment to a future slot resets notification_sent to false', function (): void {
        Carbon::setTestNow(Carbon::parse('2026-06-01 10:00:00', 'UTC'));

        $dentist = StaffMember::factory()->create(['status' => 'Active']);
        $patient = Patient::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => '2026-06-02',
            'time' => '14:00',
            'status' => 'Upcoming',
        ]);

        $appointment->forceFill(['notification_sent' => true])->saveQuietly();

        $appointment->update([
            'date' => '2026-06-03',
            'time' => '14:00',
        ]);

        expect((bool) $appointment->fresh()->notification_sent)->toBeFalse();
    });

    it('duplicate reminder job runs do not send twice for the same appointment', function (): void {
        Carbon::setTestNow(Carbon::parse('2026-05-11 12:00:00', 'UTC'));

        $dentist = StaffMember::factory()->create(['status' => 'Active']);
        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => '2026-05-11',
            'time' => '12:15',
            'status' => 'Upcoming',
        ]);

        Event::fake([NotificationCreated::class]);

        SendAppointmentReminders::dispatchSync($this->clinic);
        SendAppointmentReminders::dispatchSync($this->clinic);

        Event::assertDispatchedTimes(NotificationCreated::class, 1);
    });

    it('when NotificationService send fails notification_sent stays false and retry can succeed', function (): void {
        Carbon::setTestNow(Carbon::parse('2026-05-11 12:00:00', 'UTC'));

        $dentist = StaffMember::factory()->create(['status' => 'Active']);
        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => '2026-05-11',
            'time' => '12:15',
            'status' => 'Upcoming',
        ]);

        $mock = Mockery::mock(NotificationService::class)->makePartial();
        $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('fail'));
        app()->instance(NotificationService::class, $mock);

        expect(fn () => SendAppointmentReminders::dispatchSync($this->clinic))->toThrow(RuntimeException::class);

        expect((bool) Appointment::query()->where('dentist_id', $dentist->id)->value('notification_sent'))->toBeFalse();

        app()->forgetInstance(NotificationService::class);

        Event::fake([NotificationCreated::class]);
        SendAppointmentReminders::dispatchSync($this->clinic);

        Event::assertDispatchedTimes(NotificationCreated::class, 1);
        expect((bool) Appointment::query()->where('dentist_id', $dentist->id)->value('notification_sent'))->toBeTrue();
    });

    it('shouldSendReminderNow returns false when starts_at is not after now UTC', function (): void {
        $svc = app(AppointmentReminderService::class);
        $method = new ReflectionMethod(AppointmentReminderService::class, 'shouldSendReminderNow');
        $method->setAccessible(true);

        Carbon::setTestNow(Carbon::parse('2026-05-11 12:20:00', 'UTC'));
        $appointment = new Appointment;
        $appointment->forceFill([
            'starts_at' => CarbonImmutable::parse('2026-05-11 12:15:00', 'UTC'),
        ]);

        expect($method->invoke($svc, $appointment))->toBeFalse();

        Carbon::setTestNow(Carbon::parse('2026-05-11 12:10:00', 'UTC'));
        expect($method->invoke($svc, $appointment))->toBeTrue();
    });
});
