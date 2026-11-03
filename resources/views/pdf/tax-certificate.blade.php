@php
    /** @var \App\Support\Documents\Templates\DocumentOptions $options */
    /** @var \App\Models\Employee $employee */
    $money = fn ($value) => $options->money($value, $currency);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Salary tax certificate</title>
    <style>
        @page { margin: 32px 36px 44px 36px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9.5px; color: #1f2937; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        .title { font-size: 15px; font-weight: bold; letter-spacing: 0.4px; color: #111827; }
        .company-name { font-size: 13px; font-weight: bold; }
        .box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; }
        .details td { padding: 1.5px 0; vertical-align: top; }
        .details .key { color: #6b7280; width: 40%; }
        .items th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; color: #374151; padding: 6px 6px; border-bottom: 1px solid #d1d5db; text-align: left; }
        .items td { padding: 5px 6px; border-bottom: 1px solid #eef0f2; }
        .items .sum td { font-weight: bold; border-top: 1.5px solid #111827; border-bottom: none; }
        .sign-line { border-bottom: 1px solid #9ca3af; height: 40px; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="footer">{{ $department?->displayName() }} · Issued {{ $options->date($issuedOn) }}</div>

<table>
    <tr>
        <td style="width: 60%; vertical-align: top;">
            @if($logo)
                <img src="{{ $logo }}" alt="" style="max-height: 56px; max-width: 220px; margin-bottom: 6px;"><br>
            @endif
            <div class="company-name">{{ $department?->displayName() }}</div>
            @foreach(($department?->addressLines() ?? []) as $line)
                <div>{{ $line }}</div>
            @endforeach
            @if($department?->ntn)<div>NTN: {{ $department->ntn }}</div>@endif
        </td>
        <td style="width: 40%; vertical-align: top;" class="right">
            <div class="title">SALARY &amp; INCOME TAX CERTIFICATE</div>
            <div style="font-size: 11px; font-weight: bold; margin-top: 3px;">{{ $taxYear }}</div>
        </td>
    </tr>
</table>

<p style="margin-top: 16px;">
    This is to certify that the salary below was paid to the employee named here, and that the income tax shown
    was deducted from it under section 149 of the Income Tax Ordinance, 2001 and paid to the government.
</p>

<div class="box" style="margin-top: 10px;">
    <div class="label">Employee</div>
    <table class="details" style="margin-top: 4px;">
        <tr><td class="key">Name</td><td style="font-weight: bold;">{{ $employee->name }}</td></tr>
        @if($employee->father_name)<tr><td class="key">Father's / husband's name</td><td>{{ $employee->father_name }}</td></tr>@endif
        <tr><td class="key">CNIC</td><td>{{ $employee->cnic ?? '—' }}</td></tr>
        <tr><td class="key">Employee code</td><td>{{ $employee->code }}</td></tr>
        @if($employee->designation)<tr><td class="key">Designation</td><td>{{ $employee->designation }}</td></tr>@endif
    </table>
</div>

<table class="items" style="margin-top: 14px;">
    <thead>
    <tr>
        <th>Month</th>
        <th>Payslip no.</th>
        <th class="right">Gross salary</th>
        <th class="right">Taxable salary</th>
        <th class="right">Income tax deducted</th>
    </tr>
    </thead>
    <tbody>
    @if($opening)
        <tr>
            <td colspan="2">Before {{ $payslips->first()->periodLabel() }} <span class="muted">(salary paid before)</span></td>
            <td class="right muted">—</td>
            <td class="right">{{ $money($opening['taxable']) }}</td>
            <td class="right">{{ $money($opening['tax']) }}</td>
        </tr>
    @endif
    @foreach($payslips as $payslip)
        <tr>
            <td>{{ $payslip->periodLabel() }}</td>
            <td>{{ $payslip->number }}</td>
            <td class="right">{{ $money($payslip->gross) }}</td>
            <td class="right">{{ $money($payslip->taxable_income) }}</td>
            <td class="right">{{ $money($payslip->income_tax) }}</td>
        </tr>
    @endforeach
    <tr class="sum">
        <td colspan="2">Total</td>
        <td class="right">{{ $money($totals['gross']) }}</td>
        <td class="right">{{ $money($totals['taxable']) }}</td>
        <td class="right">{{ $money($totals['tax']) }}</td>
    </tr>
    </tbody>
</table>

<table style="margin-top: 46px;">
    <tr>
        <td style="width: 55%;"></td>
        <td style="width: 45%;">
            <div class="sign-line"></div>
            <div class="muted" style="margin-top: 3px;">Signature and stamp of the employer</div>
        </td>
    </tr>
</table>
</body>
</html>
