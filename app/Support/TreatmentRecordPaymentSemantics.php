<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant\TreatmentRecord;

final class TreatmentRecordPaymentSemantics
{
    public static function isEffectivePaid(mixed $price, mixed $paymentStatus): bool
    {
        return self::effectivePaymentStatus($price, $paymentStatus) === 'paid';
    }

    /**
     * @return 'paid'|'pending'
     */
    public static function effectivePaymentStatus(mixed $price, mixed $paymentStatus): string
    {
        $priceFloat = (float) $price;
        $status = trim((string) $paymentStatus);

        if ($priceFloat <= 0.0) {
            return 'paid';
        }

        return strcasecmp($status, 'Paid') === 0 ? 'paid' : 'pending';
    }

    /**
     * @param  iterable<int, TreatmentRecord>  $records
     * @return array{total: float, paid: float, pending: float}
     */
    public static function sumPaidPendingTotals(iterable $records): array
    {
        $totalPaid = 0.0;
        $totalPending = 0.0;
        $sumPrices = 0.0;

        foreach ($records as $r) {
            /** @var TreatmentRecord $r */
            $price = (float) $r->price;
            $sumPrices += $price;
            if (self::isEffectivePaid($price, (string) $r->payment_status)) {
                $totalPaid += $price;
            } else {
                $totalPending += $price;
            }
        }

        return [
            'total' => round($sumPrices, 2),
            'paid' => round($totalPaid, 2),
            'pending' => round($totalPending, 2),
        ];
    }
}
