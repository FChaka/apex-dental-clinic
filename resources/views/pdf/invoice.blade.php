<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; margin: 24px;">
@php
    $subtotal = (float) $invoice->amount;
    $vatRate = $invoice->vat_rate !== null ? (float) $invoice->vat_rate : 0.0;
    $vatAmount = $vatRate > 0 ? round($subtotal * ($vatRate / 100), 2) : 0.0;
    $total = round($subtotal + $vatAmount, 2);
@endphp
<table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td width="60%" valign="top">
            <h1 style="font-size: 20px; margin: 0 0 8px 0;">Invoice</h1>
            <p style="margin: 0; line-height: 1.5;">
                <strong>{{ $clinicSetting?->clinic_name ?? 'Clinic' }}</strong><br/>
                @if($clinicSetting?->address){{ $clinicSetting->address }}<br/>@endif
                @if($clinicSetting?->city){{ $clinicSetting->city }}@if($clinicSetting?->zip_code) {{ $clinicSetting->zip_code }}@endif<br/>@endif
                @if($clinicSetting?->phone)Tel: {{ $clinicSetting->phone }}<br/>@endif
                @if($clinicSetting?->email)Email: {{ $clinicSetting->email }}@endif
            </p>
        </td>
        <td width="40%" valign="top" align="right">
            <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #e5e7eb;">
                <tr><td style="background: #f3f4f6;"><strong>Invoice #</strong></td><td>{{ $invoice->invoice_number }}</td></tr>
                <tr><td style="background: #f3f4f6;"><strong>Date</strong></td><td>{{ $invoice->date?->format('Y-m-d') }}</td></tr>
                <tr><td style="background: #f3f4f6;"><strong>Due</strong></td><td>{{ $invoice->due_date?->format('Y-m-d') }}</td></tr>
                <tr><td style="background: #f3f4f6;"><strong>Status</strong></td><td>{{ $invoice->status }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<h2 style="font-size: 14px; margin: 24px 0 8px 0;">Bill to</h2>
<p style="margin: 0; line-height: 1.5;">
    <strong>{{ $patient?->name }} {{ $patient?->surname }}</strong><br/>
    @if($patient?->address){{ $patient->address }}<br/>@endif
    @if($patient?->city){{ $patient->city }}<br/>@endif
    @if($patient?->phone)Tel: {{ $patient->phone }}<br/>@endif
    @if($patient?->email)Email: {{ $patient->email }}@endif
</p>

<h2 style="font-size: 14px; margin: 24px 0 8px 0;">Treatment lines</h2>
<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #e5e7eb;">
    <thead>
    <tr style="background: #f3f4f6;">
        <th align="left">Treatment</th>
        <th align="left">Tooth</th>
        <th align="left">Dentist</th>
        <th align="right">Price</th>
    </tr>
    </thead>
    <tbody>
    @forelse($invoice->treatmentEntries as $entry)
        <tr>
            <td>{{ $entry->treatmentType?->name ?? '—' }}</td>
            <td>{{ $entry->tooth_number ?? '—' }}</td>
            <td>{{ $entry->dentist?->name ?? '—' }}</td>
            <td align="right">{{ number_format((float) $entry->price, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" align="center">No line items</td></tr>
    @endforelse
    </tbody>
</table>

<table width="40%" cellpadding="6" cellspacing="0" border="0" align="right" style="margin-top: 16px;">
    <tr>
        <td align="right"><strong>Subtotal</strong></td>
        <td align="right">{{ number_format($subtotal, 2) }}</td>
    </tr>
    <tr>
        <td align="right"><strong>VAT @if($vatRate > 0)({{ number_format($vatRate, 2) }}%)@endif</strong></td>
        <td align="right">{{ number_format($vatAmount, 2) }}</td>
    </tr>
    <tr>
        <td align="right"><strong>Total</strong></td>
        <td align="right"><strong>{{ number_format($total, 2) }}</strong></td>
    </tr>
</table>

<div style="clear: both;"></div>

@if($invoiceSetting)
    <h2 style="font-size: 14px; margin: 32px 0 8px 0;">Payment details</h2>
    <p style="margin: 0; line-height: 1.5;">
        @if($invoiceSetting->bank_name)<strong>Bank:</strong> {{ $invoiceSetting->bank_name }}<br/>@endif
        @if($invoiceSetting->iban)<strong>IBAN:</strong> {{ $invoiceSetting->iban }}<br/>@endif
        @if($invoiceSetting->swift)<strong>SWIFT:</strong> {{ $invoiceSetting->swift }}<br/>@endif
        @if($invoiceSetting->account_holder)<strong>Account holder:</strong> {{ $invoiceSetting->account_holder }}<br/>@endif
        @if($invoiceSetting->other_details){!! nl2br(e($invoiceSetting->other_details)) !!}@endif
    </p>
@endif
</body>
</html>
