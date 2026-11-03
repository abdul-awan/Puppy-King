{{-- A horizontal line, as wide as chosen and placed by the alignment. --}}
@php
    $place = match ($element['style']['align'] ?? 'left') {
        'center' => ' margin-left: auto; margin-right: auto;',
        'right' => ' margin-left: auto;',
        default => '',
    };
@endphp
<div style="border-top: {{ $css->line((float) $s['thickness'], $s['line'], $s['color']) }}; width: {{ $s['width'] }}%;{{ $place }} height: 0; font-size: 0; line-height: 0;"></div>
