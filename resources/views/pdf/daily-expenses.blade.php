@php
    /** @var \App\Support\Documents\Templates\DocumentOptions $options */
    $money = fn ($value) => $options->money((string) $value, $currency);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily expenses</title>
    <style>
        @page { margin: 28px 32px 40px 32px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9px; color: #1f2937; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .title { font-size: 18px; font-weight: bold; letter-spacing: 0.5px; color: #111827; }
        .company-name { font-size: 12px; font-weight: bold; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; }
        .heading { font-size: 10.5px; font-weight: bold; margin: 14px 0 4px 0; }
        .items th { background: #f3f4f6; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.3px; color: #374151; padding: 5px 4px; border-bottom: 1px solid #d1d5db; text-align: left; }
        .items td { padding: 4px 4px; border-bottom: 1px solid #eef0f2; vertical-align: top; }
        .items .day td { background: #f9fafb; font-weight: bold; border-top: 1px solid #d1d5db; }
        .items .grand td { font-size: 10.5px; font-weight: bold; border-top: 1.5px solid #111827; border-bottom: none; padding-top: 6px; }
        .box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; }
        .summary td { padding: 2px 0; }
        .sign-line { border-bottom: 1px solid #9ca3af; height: 36px; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="footer">
    {{ $department?->displayName() }} · Daily expenses {{ $period }} · Printed {{ $options->date($printedAt) }} {{ $printedAt->format('H:i') }}
</div>

<table>
    <tr>
        <td style="width: 60%; vertical-align: top;">
            @if($logo)
                <img src="{{ $logo }}" alt="" style="max-height: 50px; max-width: 200px; margin-bottom: 6px;"><br>
            @endif
            <div class="company-name">{{ $department?->displayName() }}</div>
            @foreach(($department?->addressLines() ?? []) as $line)
                <div>{{ $line }}</div>
            @endforeach
        </td>
        <td style="width: 40%; vertical-align: top;" class="right">
            <div class="title">DAILY EXPENSES</div>
            <div style="font-size: 11px; font-weight: bold; margin-top: 2px;">{{ $period }}</div>
            @if($filter)<div class="muted">{{ $filter }}</div>@endif
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 14px;">
    <thead>
    <tr>
        <th style="width: 11%;">No.</th>
        <th style="width: 16%;">Category</th>
        <th>Description</th>
        <th style="width: 15%;">Paid to</th>
        <th style="width: 12%;">Paid by</th>
        <th style="width: 9%;">Paid with</th>
        <th class="right" style="width: 12%;">Amount</th>
    </tr>
    </thead>
    <tbody>
    @forelse($days as $day)
        <tr class="day">
            <td colspan="6">{{ $day['date']->format('l') }}, {{ $options->date($day['date']) }}</td>
            <td class="right">{{ $money($day['total']) }}</td>
        </tr>
        @foreach($day['expenses'] as $expense)
            <tr>
                <td>{{ $expense->number }}</td>
                <td>{{ $expense->category?->name }}</td>
                <td>{{ $expense->description }}@if($expense->reference)<div class="muted">Receipt {{ $expense->reference }}</div>@endif</td>
                <td>{{ $expense->paid_to }}</td>
                <td>{{ $expense->paid_by }}</td>
                <td>{{ $expense->payment_method->value === 'cash' ? 'Cash' : $expense->payment_method->label() }}</td>
                <td class="right">{{ $money($expense->amount) }}</td>
            </tr>
        @endforeach
    @empty
        <tr><td colspan="7" class="muted">No expenses in this period.</td></tr>
    @endforelse
    <tr class="grand">
        <td colspan="6">Total ({{ $count }} {{ $count === 1 ? 'expense' : 'expenses' }})</td>
        <td class="right">{{ $money($total) }}</td>
    </tr>
    </tbody>
</table>

<table style="margin-top: 16px;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding-right: 10px;">
            @if(count($categories) > 0)
                <div class="box">
                    <div class="label">Per category</div>
                    <table class="summary" style="margin-top: 4px;">
                        @foreach($categories as $category)
                            <tr>
                                <td>{{ $category['name'] }} <span class="muted">({{ $category['count'] }})</span></td>
                                <td class="right">{{ $money($category['total']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </td>
        <td style="width: 50%; vertical-align: top;">
            @if($cash)
                <div class="box">
                    <div class="label">Cash box</div>
                    <table class="summary" style="margin-top: 4px;">
                        <tr><td>Cash at the start</td><td class="right">{{ $money($cash['opening']) }}</td></tr>
                        <tr><td>Cash added</td><td class="right">{{ $money($cash['added']) }}</td></tr>
                        @if((float) $cash['taken_out'] > 0)
                            <tr><td>Cash taken out</td><td class="right">− {{ $money($cash['taken_out']) }}</td></tr>
                        @endif
                        <tr><td>Expenses paid in cash</td><td class="right">− {{ $money($cash['expenses']) }}</td></tr>
                        <tr><td style="font-weight: bold; border-top: 1px solid #d1d5db; padding-top: 3px;">Cash in hand at the end</td><td class="right" style="font-weight: bold; border-top: 1px solid #d1d5db; padding-top: 3px;">{{ $money($cash['closing']) }}</td></tr>
                    </table>
                </div>
            @endif
        </td>
    </tr>
</table>

<table style="margin-top: 34px;">
    <tr>
        <td style="width: 30%;"><div class="sign-line"></div><div class="muted" style="margin-top: 3px;">Prepared by</div></td>
        <td style="width: 5%;"></td>
        <td style="width: 30%;"><div class="sign-line"></div><div class="muted" style="margin-top: 3px;">Checked by</div></td>
        <td style="width: 5%;"></td>
        <td style="width: 30%;"><div class="sign-line"></div><div class="muted" style="margin-top: 3px;">Approved by</div></td>
    </tr>
</table>
</body>
</html>
