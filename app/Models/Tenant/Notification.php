<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Notification extends Model
{
    protected $table = 'notifications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'receiver_staff_id',
        'from_staff_id',
        'type',
        'message',
        'path',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'receiver_staff_id');
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'from_staff_id');
    }
}
