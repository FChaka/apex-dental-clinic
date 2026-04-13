<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    protected $table = 'invoice_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_name',
        'iban',
        'swift',
        'account_holder',
        'other_details',
    ];
}
