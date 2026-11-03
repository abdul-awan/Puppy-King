@php
    /** @var \App\Support\Documents\Templates\DocumentOptions $options */
    $money = fn ($value) => $options->money($value, $currency);
    $days = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        @page { margin: 28px 32px 40px 32px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9.5px; color: #1f2937; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .title { font-size: 20px; font-weight: bold; letter-spacing: 0.5px; color: #111827; }
        .company-name { font-size: 13px; font-weight: bold; }
        .box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; }
        .details td { padding: 1.5px 0; vertical-align: top; }
        .details .key { color: #6b7280; width: 38%; }
        .lines th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; color: #374151; padding: 6px 6px; border-bottom: 1px solid #d1d5db; text-align: left; }
        .lines td { padding: 5px 6px; border-bottom: 1px solid #eef0f2; vertical-align: top; }
        .lines .sum td { font-weight: bold; border-bottom: none; border-top: 1px solid #d1d5db; }
        .net { border: 1.5px solid #111827; border-radius: 4px; padding: 10px 12px; margin-top: 14px; }
        .net-amount { font-size: 17px; font-weight: bold; }
        .stamp { font-size: 11px; font-weight: bold; padding: 4px 10px; border: 2px solid; border-radius: 4px; display: inline-block; }
        .stamp-draft { color: #6b7280; border-color: #6b7280; }
        .stamp-paid { color: #15803d; border-color: #15803d; }
        .sign-line { border-bottom: 1px solid #9ca3af; height: 40px; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
        .pre { white-space: pre-line; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
<div class="footer">
    {{ $department?->displayName() }}@if($department?->ntn) · NTN {{ $department->ntn }}@endif · Private and confidential
</div>

@foreach($slips as $slip)
    @php
        /** @var \App\Models\Payslip $payslip */
        $payslip = $slip['payslip'];
    @endphp
    <div @class(['page-break' => ! $loop->last])>
        <table>
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    @if($logo)
                        <img src="{{ $logo }}" alt="" style="max-height: 60px; max-width: 220px; margin-bottom: 6px;"><br>
                    @endif
                    <div class="company-name">{{ $department?->displayName() }}</div>
                    @foreach(($department?->addressLines() ?? []) as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                    @if($department?->ntn)<div>NTN: {{ $department->ntn }}</div>@endif
                </td>
                <td style="width: 45%; vertical-align: top;" class="right">
                    <div class="title">PAYSLIP</div>
                    <div style="font-size: 12px; font-weight: bold; margin-top: 2px;">{{ $payslip->periodLabel() }}</div>
                    @if($slip['stamp'])
                        <div style="margin-top: 6px;">
                            <span class="stamp stamp-{{ strtolower($slip['stamp']) }}">{{ $slip['stamp'] }}</span>
                        </div>
                    @endif
                    <table style="margin-top: 8px;">
                        @if($payslip->number)
                            <tr><td class="right muted" style="width: 55%;">Payslip no.</td><td class="right" style="font-weight: bold;">{{ $payslip->number }}</td></tr>
                        @endif
                        @if($slip['payDate'])
                            <tr><td class="right muted">{{ $payslip->isPaid() ? 'Paid on' : 'Pay date' }}</td><td class="right" style="font-weight: bold;">{{ $options->date($slip['payDate']) }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <table style="margin-top: 14px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                    <div class="box">
                        <div class="label">Employee</div>
                        <div style="font-weight: bold; font-size: 11px; margin: 2px 0 4px 0;">{{ $payslip->employee_name }}</div>
                        <table class="details">
                            <tr><td class="key">Employee code</td><td>{{ $payslip->employee_code }}</td></tr>
                            @if($payslip->designation)<tr><td class="key">Designation</td><td>{{ $payslip->designation }}</td></tr>@endif
                            @if($payslip->department)<tr><td class="key">Department</td><td>{{ $payslip->department }}</td></tr>@endif
                            @if($payslip->cnic)<tr><td class="key">CNIC</td><td>{{ $payslip->cnic }}</td></tr>@endif
                            <tr><td class="key">Joining date</td><td>{{ $options->date($slip['joiningDate']) }}</td></tr>
                        </table>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <div class="box">
                        <div class="label">Payment</div>
                        <table class="details" style="margin-top: 4px;">
                            <tr><td class="key">Paid by</td><td>{{ $payslip->payment_method->label() }}</td></tr>
                            @if($payslip->bank_name)<tr><td class="key">Bank</td><td>{{ $payslip->bank_name }}</td></tr>@endif
                            @if($payslip->bank_account_title)<tr><td class="key">Account title</td><td>{{ $payslip->bank_account_title }}</td></tr>@endif
                            @if($payslip->bank_account_number)<tr><td class="key">Account / IBAN</td><td>{{ $payslip->bank_account_number }}</td></tr>@endif
                            <tr><td class="key">Days paid</td><td>{{ $days($payslip->payable_days) }} of {{ $payslip->days_in_period }}@if((float) $payslip->unpaid_days > 0) ({{ $days($payslip->unpaid_days) }} unpaid)@endif</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table style="margin-top: 14px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                    <table class="lines">
                        <thead><tr><th>Earnings</th><th class="right" style="width: 38%;">Amount</th></tr></thead>
                        <tbody>
                        @forelse($slip['earnings'] as $line)
                            <tr><td>{{ $line->name }}</td><td class="right">{{ $money($line->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">No earnings</td></tr>
                        @endforelse
                        <tr class="sum"><td>Gross pay</td><td class="right">{{ $money($payslip->gross) }}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <table class="lines">
                        <thead><tr><th>Deductions</th><th class="right" style="width: 38%;">Amount</th></tr></thead>
                        <tbody>
                        @forelse($slip['deductions'] as $line)
                            <tr><td>{{ $line->name }}</td><td class="right">{{ $money($line->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">No deductions</td></tr>
                        @endforelse
                        <tr class="sum"><td>Total deductions</td><td class="right">{{ $money($payslip->deductions) }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <div class="net">
            <table>
                <tr>
                    <td style="vertical-align: middle;">
                        <div class="label">Net pay</div>
                        <div class="muted" style="margin-top: 2px;">{{ $slip['words'] }}</div>
                    </td>
                    <td class="right net-amount" style="vertical-align: middle; width: 40%;">{{ $money($payslip->net) }}</td>
                </tr>
            </table>
        </div>

        <table style="margin-top: 14px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                    <div class="box">
                        <div class="label">Income tax · {{ $slip['taxYear'] }}</div>
                        <table class="details" style="margin-top: 4px;">
                            <tr><td class="key" style="width: 62%;">Taxable pay this month</td><td class="right">{{ $money($payslip->taxable_income) }}</td></tr>
                            <tr><td class="key">Tax deducted this month</td><td class="right">{{ $money($payslip->income_tax) }}</td></tr>
                            <tr><td class="key">Taxable pay this tax year</td><td class="right">{{ $money($slip['yearToDate']['taxable']) }}</td></tr>
                            <tr><td class="key">Tax deducted this tax year</td><td class="right">{{ $money($slip['yearToDate']['tax']) }}</td></tr>
                        </table>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    @if($slip['employer']->isNotEmpty())
                        <div class="box">
                            <div class="label">Paid by the employer on top of the salary</div>
                            <table class="details" style="margin-top: 4px;">
                                @foreach($slip['employer'] as $line)
                                    <tr><td class="key" style="width: 62%;">{{ $line->name }}</td><td class="right">{{ $money($line->amount) }}</td></tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </td>
            </tr>
        </table>

        @if(filled($payslip->notes))
            <div style="margin-top: 12px;">
                <div class="label">Remarks</div>
                <div class="pre">{{ $payslip->notes }}</div>
            </div>
        @endif

        @if($note)
            <div class="pre muted" style="margin-top: 12px;">{{ $note }}</div>
        @endif

        <table style="margin-top: 34px;">
            <tr>
                <td style="width: 40%;"><div class="sign-line"></div><div class="muted" style="margin-top: 3px;">Employee's signature</div></td>
                <td style="width: 20%;"></td>
                <td style="width: 40%;"><div class="sign-line"></div><div class="muted" style="margin-top: 3px;">Authorised signature</div></td>
            </tr>
        </table>
    </div>
@endforeach
</body>
</html>
