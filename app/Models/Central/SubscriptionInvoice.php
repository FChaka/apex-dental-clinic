<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    protected $connection = 'central';

    protected $table = 'subscription_invoices';

    protected $fillable = [
        'subscription_id',
        'clinic_id',
        'amount',
        'status',
        'issued_at',
        'paid_at',
        'due_at',
        'external_id',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * @return BelongsTo<Clinic, $this>
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'paid_at' => 'date',
            'due_at' => 'date',
            'amount' => 'decimal:2',
        ];
    }
}
