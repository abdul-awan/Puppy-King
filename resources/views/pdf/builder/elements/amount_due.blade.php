{{-- The total, or the balance due of a part-paid invoice, in large type. --}}
@php $highlight = $context->highlight(); @endphp
<div style="{{ $labelStyle($bg) }}">{{ filled($s['label']) ? $s['label'] : $highlight['label'] }}</div>
<div style="font-size: {{ $px($s['value_size']) }}; font-weight: bold; color: {{ $palette->text($s['value_color'], $bg) }}; margin-top: 2px; white-space: nowrap;">{{ $highlight['value'] }}</div>
