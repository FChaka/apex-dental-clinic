<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\Patient;
use Illuminate\Support\Str;
use LogicException;

final class TenantPatientStoragePaths
{
    private const SLUG_PART_MAX_LENGTH = 80;

    public static function patientDirectorySegment(Patient $patient): string
    {
        $nameSlug = self::slugPart($patient->name);
        $surnameSlug = self::slugPart($patient->surname);
        $id = (string) $patient->id;

        $parts = array_values(array_filter([$nameSlug, $surnameSlug], static fn (string $p): bool => $p !== ''));

        if ($parts === []) {
            return 'patient-'.$id;
        }

        return implode('-', $parts).'-'.$id;
    }

    public static function documentsDirectory(Patient $patient): string
    {
        $tenantSlug = (string) tenancy()->tenant->slug;
        $segment = self::patientDirectorySegment($patient);

        return "tenants/{$tenantSlug}/patients/{$segment}/documents";
    }

    public static function xrayDirectory(Patient $patient): string
    {
        $tenantSlug = (string) tenancy()->tenant->slug;
        $segment = self::patientDirectorySegment($patient);

        return "tenants/{$tenantSlug}/patients/{$segment}/xrays";
    }

    public static function xrayThumbsDirectory(Patient $patient): string
    {
        return self::xrayDirectory($patient).'/thumbs';
    }

    public static function invoicePdfRelativePath(Invoice $invoice): string
    {
        if (! $invoice->relationLoaded('patient') || $invoice->patient === null) {
            throw new LogicException('Patient relation must be loaded to compute invoice PDF path.');
        }

        $tenantSlug = (string) tenancy()->tenant->slug;
        $segment = self::patientDirectorySegment($invoice->patient);

        return "tenants/{$tenantSlug}/patients/{$segment}/invoices/{$invoice->invoice_number}.pdf";
    }

    private static function slugPart(?string $value): string
    {
        $slug = Str::slug((string) ($value ?? ''));

        if ($slug === '') {
            return '';
        }

        return Str::substr($slug, 0, self::SLUG_PART_MAX_LENGTH);
    }
}
