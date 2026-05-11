<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WidgetPreference extends Model
{
    protected $table = 'user_widget_preferences';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_id',
        'page',
        'widget_order',
    ];

    protected function casts(): array
    {
        return [
            'widget_order' => 'array',
        ];
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'staff_id');
    }
}
