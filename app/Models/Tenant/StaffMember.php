<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\StaffMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class StaffMember extends Authenticatable
{
    /** @use HasFactory<StaffMemberFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
        'color',
        'annual_leave_days',
        'leave_start',
        'leave_end',
        'paid_by_percentage',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'login_pin',
        'login_password',
    ];

    protected function casts(): array
    {
        return [
            'login_password' => 'hashed',
            'paid_by_percentage' => 'boolean',
            'leave_start' => 'date',
            'leave_end' => 'date',
            'pin_length' => 'integer',
            'annual_leave_days' => 'integer',
        ];
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
