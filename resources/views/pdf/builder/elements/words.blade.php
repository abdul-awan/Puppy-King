{{-- The total in words. --}}
@if($s['show_label'] && filled($s['label']))
    <div style="{{ $labelStyle($bg) }}">{{ $s['label'] }}</div>
@endif
<div style="margin-top: 2px;">{{ $amountInWords }}</div>
