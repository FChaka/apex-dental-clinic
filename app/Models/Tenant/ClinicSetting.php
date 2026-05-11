<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    protected $table = 'clinic_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clinic_name',
        'phone',
        'email',
        'address',
        'website',
        'business_nr',
        'city',
        'zip_code',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'logo_path',
        'brand_color',
        'currency',
        'default_vat',
    ];

    protected function casts(): array
    {
        return [
            'default_vat' => 'decimal:2',
        ];
    }
}
