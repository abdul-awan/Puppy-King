{{-- The customer: name, address and tax numbers, with a small label or a coloured bar above. --}}
@php
    $heading = filled($s['heading']) ? $s['heading'] : $customer['heading'];
@endphp
@if($s['heading_style'] === 'bar')
    @php $barBackground = $palette->background($s['bar_background']) ?? (string) $palette->background('soft'); @endphp
    @if($s['show_heading'])
        <div style="background-color: {{ $barBackground }}; color: {{ $palette->text('accent', $barBackground) }}; font-size: {{ $px($page->scale(7.5)) }}; font-weight: bold; text-transform: uppercase; letter-spacing: {{ $px($page->labelSpacing) }}; padding: 4px 8px;">{{ $heading }}</div>
    @endif
    <div style="padding: 7px 8px;">
        @include('pdf.builder.elements.parts.customer-body')
    </div>
@else
    @if($s['show_heading'])
        <div style="{{ $labelStyle($bg) }}">{{ $heading }}</div>
    @endif
    @include('pdf.builder.elements.parts.customer-body')
@endif
