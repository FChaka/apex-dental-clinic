<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\TreatmentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentType extends Model
{
    /** @use HasFactory<TreatmentTypeFactory> */
    use HasFactory;

    protected $table = 'treatment_types';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'default_duration',
        'default_price',
        'vat',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'vat' => 'decimal:2',
            'is_active' => 'boolean',
            'default_duration' => 'integer',
        ];
    }

    public function treatmentEntries(): HasMany
    {
        return $this->hasMany(PatientTreatmentEntry::class, 'treatment_type_id');
    }

    protected static function newFactory(): TreatmentTypeFactory
    {
        return TreatmentTypeFactory::new();
    }
}
