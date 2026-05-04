<?php

declare(strict_types=1);

namespace App\Models\Central;

use Database\Factories\Central\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'subscriptions';

    protected $fillable = [
        'clinic_id',
        'plan',
        'amount',
        'status',
        'starts_at',
        'renews_at',
        'canceled_at',
        'payment_method',
        'external_id',
        'canceled_at',
    ];

    /**
     * @return BelongsTo<Clinic, $this>
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
    }

    /**
     * @return HasMany<SubscriptionInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'subscription_id');
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'renews_at' => 'date',
            'canceled_at' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }
}
