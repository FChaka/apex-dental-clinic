<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Http\Controllers\Api\Clinic\LeaveRequestController;
use Database\Factories\Tenant\StaffMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class StaffMember extends Authenticatable
{
    /** @use HasFactory<StaffMemberFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'staff_members';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar_path',
        'role',
        'clinic_access_level',
        'specialty',
        'experience',
        'status',
        'username',
        'sign_in_method',
        'pin_length',
        'login_pin',
        'login_password',
        'color',
        'annual_leave_days',
        'leave_start',
        'leave_end',
        'paid_by_percentage',
        'temp_pin_expires_at',
        'must_change_credentials',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'login_pin',
        'login_password',
    ];

    /**
     * Staff who receive alerts when others submit leave requests ({@see LeaveRequestController::canManageOtherLeave}).
     *
     * @param  Builder<StaffMember>  $query
     * @return Builder<StaffMember>
     */
    public function scopeForLeaveManagementAlerts(Builder $query): Builder
    {
        return $query
            ->where('status', 'Active')
            ->whereIn('clinic_access_level', ['super_admin', 'admin']);
    }

    protected function casts(): array
    {
        return [
            'login_pin' => 'hashed',
            'login_password' => 'hashed',
            'paid_by_percentage' => 'boolean',
            'leave_start' => 'date',
            'leave_end' => 'date',
            'pin_length' => 'integer',
            'annual_leave_days' => 'integer',
            'must_change_credentials' => 'boolean',
            'temp_pin_expires_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StaffWorkingSchedule, $this>
     */
    public function workingSchedules(): HasMany
    {
        return $this->hasMany(StaffWorkingSchedule::class, 'staff_id');
    }

    /**
     * @return HasMany<StaffDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(StaffDocument::class, 'staff_id');
    }

    /**
     * @return HasMany<StaffPercentagePerTreatment, $this>
     */
    public function percentagePerTreatment(): HasMany
    {
        return $this->hasMany(StaffPercentagePerTreatment::class, 'staff_id');
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'receiver_staff_id');
    }

    public function getAuthPassword(): ?string
    {
        return $this->login_password;
    }

    protected static function newFactory(): StaffMemberFactory
    {
        return StaffMemberFactory::new();
    }
}
