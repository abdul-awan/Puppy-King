@php
    /** @var \App\Models\WorkOrder $workOrder */
    $money = fn ($value) => number_format((float) $value, 2);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    $hours = fn (int $minutes) => sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Work order {{ $workOrder->number }}</title>
    <style>
        @page { margin: 28px 32px 40px 32px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9.5px; color: #1f2937; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        .title { font-size: 20px; font-weight: bold; letter-spacing: 0.5px; color: #111827; }
        .company-name { font-size: 13px; font-weight: bold; }
        .box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; }
        .heading { font-size: 10.5px; font-weight: bold; margin: 14px 0 4px 0; }
        .items th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; color: #374151; padding: 6px 5px; border-bottom: 1px solid #d1d5db; text-align: left; }
        .items td { padding: 6px 5px; border-bottom: 1px solid #eef0f2; vertical-align: top; }
        .items .sum td { font-weight: bold; border-bottom: none; border-top: 1px solid #d1d5db; }
        .totals td { padding: 3px 5px; }
        .totals .grand td { font-size: 11px; font-weight: bold; border-top: 1.5px solid #111827; padding-top: 6px; }
        .stamp { font-size: 11px; font-weight: bold; padding: 4px 10px; border: 2px solid; border-radius: 4px; display: inline-block; }
        .sign-line { border-bottom: 1px solid #9ca3af; height: 46px; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
        .pre { white-space: pre-line; }
    </style>
</head>
<body>
<div class="footer">
    {{ $department?->displayName() }}@if($department?->ntn) · NTN {{ $department->ntn }}@endif
    · Work order {{ $workOrder->number }}
</div>

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
            @if($department?->phone)<div>Tel: {{ $department->phone }}</div>@endif
            @if($department?->email)<div>{{ $department->email }}</div>@endif
        </td>
        <td style="width: 45%; vertical-align: top;" class="right">
            <div class="title">WORK ORDER</div>
            @if($workOrder->isSigned())
                <div style="margin-top: 6px;">
                    <span class="stamp" style="color: #15803d; border-color: #15803d;">SIGNED</span>
                </div>
            @endif
            <table style="margin-top: 8px;">
                @foreach($meta as $label => $value)
                    <tr>
                        <td class="right muted" style="width: 55%;">{{ $label }}</td>
                        <td class="right" style="font-weight: bold;">{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

<table style="margin-top: 14px;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding-right: 10px;">
            <div class="box">
                <div class="label">Customer</div>
                <div style="font-weight: bold; font-size: 11px; margin-top: 2px;">{{ $customer['name'] }}</div>
                @if($customer['contact'])<div>Attn. {{ $customer['contact'] }}</div>@endif
                @if($customer['phone'])<div>Tel: {{ $customer['phone'] }}</div>@endif
                @if($customer['email'])<div>{{ $customer['email'] }}</div>@endif
            </div>
        </td>
        <td style="width: 50%; vertical-align: top;">
            <div class="box">
                <div class="label">Work location</div>
                <div class="pre" style="margin-top: 2px;">{{ $workOrder->location ?: '—' }}</div>
            </div>
        </td>
    </tr>
</table>

<div style="margin-top: 14px; font-size: 12px; font-weight: bold;">{{ $workOrder->title }}</div>

@if(filled($workOrder->description))
    <div class="heading">Work carried out</div>
    <div class="pre">{{ $workOrder->description }}</div>
@endif

@if($entries->isNotEmpty())
    <div class="heading">Hours</div>
    <table class="items">
        <thead>
        <tr>
            <th style="width: 13%;">Date</th>
            <th style="width: 20%;">Done by</th>
            <th>Work</th>
            <th class="right" style="width: 9%;">Hours</th>
            @if($showPrices)
                <th class="right" style="width: 12%;">Rate</th>
                <th class="right" style="width: 13%;">Amount</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach($entries as $entry)
            <tr>
                <td>{{ $entry->date->format('d M Y') }}</td>
                <td>{{ $entry->user?->name }}</td>
                <td>
                    {{ $entry->workType?->name ?? 'Work' }}
                    @if($entry->description)<div class="muted pre">{{ $entry->description }}</div>@endif
                </td>
                <td class="right">{{ $hours($entry->duration_minutes) }}</td>
                @if($showPrices)
                    <td class="right">{{ $entry->is_billable ? $money($entry->hourly_rate) : '' }}</td>
                    <td class="right">{{ $entry->is_billable ? $money($entry->value()) : 'No charge' }}</td>
                @endif
            </tr>
        @endforeach
        <tr class="sum">
            <td colspan="3">Total hours</td>
            <td class="right">{{ $hours($totals['minutes']) }}</td>
            @if($showPrices)
                <td></td>
                <td class="right">{{ $money($totals['time_value']) }}</td>
            @endif
        </tr>
        </tbody>
    </table>
@endif

@if($materials->isNotEmpty())
    <div class="heading">Materials used</div>
    <table class="items">
        <thead>
        <tr>
            <th style="width: 4%;">#</th>
            <th>Description</th>
            <th class="right" style="width: 13%;">Qty</th>
            @if($showPrices)
                <th class="right" style="width: 13%;">Unit price</th>
                <th class="right" style="width: 13%;">Amount</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach($materials as $material)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td>{{ $material->description }}</td>
                <td class="right">{{ $qty($material->quantity) }} {{ $material->unit }}</td>
                @if($showPrices)
                    <td class="right">{{ $material->is_billable ? $money($material->unit_price) : '' }}</td>
                    <td class="right">{{ $material->is_billable ? $money($material->value()) : 'No charge' }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($showPrices && ($entries->isNotEmpty() || $materials->isNotEmpty()))
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 52%; vertical-align: top;" class="muted">
                Amounts exclude sales tax. The invoice follows separately.
            </td>
            <td style="width: 48%; vertical-align: top;">
                <table class="totals">
                    <tr><td class="muted">Hours</td><td class="right">{{ $currency }} {{ $money($totals['time_value']) }}</td></tr>
                    <tr><td class="muted">Materials</td><td class="right">{{ $currency }} {{ $money($totals['materials_value']) }}</td></tr>
                    <tr class="grand"><td>Total excl. tax</td><td class="right">{{ $currency }} {{ $money($totals['total_value']) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
@endif

@if(filled($workOrder->customer_remarks))
    <div class="heading">Remarks of the customer</div>
    <div class="pre">{{ $workOrder->customer_remarks }}</div>
@endif

<table style="margin-top: 22px;">
    <tr>
        <td style="width: 48%; vertical-align: top; padding-right: 4%;" class="box">
            <div class="label">For {{ $department?->displayName() }}</div>
            <div style="margin-top: 4px;">{{ $workOrder->assignee?->name ?? ' ' }}</div>
            @if(! $workOrder->isSigned())<div class="sign-line"></div>@endif
        </td>
        <td style="width: 4%;"></td>
        <td style="width: 48%; vertical-align: top;" class="box">
            <div class="label">Signature of the customer</div>
            @if($workOrder->isSigned())
                <img src="{{ $workOrder->signature }}" alt="" style="max-height: 60px; max-width: 240px; margin-top: 4px;">
                <div>{{ $workOrder->signed_by_name }} · {{ $workOrder->signed_at?->format('d M Y H:i') }}</div>
            @else
                <div class="sign-line"></div>
                <div class="muted">Name and date</div>
            @endif
        </td>
    </tr>
</table>

@if($department?->document_footer)
    <div class="pre muted center" style="margin-top: 18px;">{{ $department->document_footer }}</div>
@endif
</body>
</html>
