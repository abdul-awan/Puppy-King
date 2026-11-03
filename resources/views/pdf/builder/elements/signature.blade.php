{{-- Lines (or boxes) to sign and stamp. One line sits on the side of the alignment; more lines share the width. --}}
@php
    $labels = $context->signatureLabels($s);
    $count = count($labels);
    $align = $element['style']['align'] ?? 'right';
    $space = (int) $s['space'];
    $box = $s['variant'] === 'box';
    $cell = 'padding-top: '.($box ? $space + 24 : $space).'px; text-align: left;'
        .($box ? ' border: 1px solid '.$palette->line('soft').'; padding-left: 8px; padding-right: 8px; padding-bottom: 6px;' : '');
    $width = $count === 1 ? 45 : (100 - 8 * ($count - 1)) / $count;
    $spacer = $align === 'center' ? 27.5 : 55;
    $muted = $palette->text('muted', $bg);
@endphp
<table>
    <tr>
        @if($count === 1 && $align !== 'left')<td style="width: {{ $spacer }}%;"></td>@endif
        @foreach($labels as $index => $label)
            @if($index > 0)<td style="width: 8%;"></td>@endif
            <td style="width: {{ $width }}%; {{ $cell }}">
                <div style="border-top: 1px solid {{ $palette->line('dark') }}; padding-top: 3px;">{{ $label }}</div>
                @if($s['show_company'] && $loop->last && filled($seller['name']))
                    <div style="color: {{ $muted }};">For {{ $seller['name'] }}</div>
                @endif
            </td>
        @endforeach
        @if($count === 1 && $align !== 'right')<td style="width: {{ $spacer }}%;"></td>@endif
    </tr>
</table>
