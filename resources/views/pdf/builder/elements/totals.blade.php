{{-- Subtotal, discount, tax, the total and (on invoices) what is paid and still due. --}}
@php
    $width = (int) $s['width'];
    $labelColor = $palette->text($s['label_color'], $bg);
    $accentInk = $palette->text('accent', $bg);
    $boxed = $s['rows_style'] === 'boxed';
    $padding = $boxed ? '4px 7px' : '3px 5px';
    $rowLine = match ($s['rows_style']) {
        'lines' => ' border-bottom: '.$css->line(1, 'solid', 'grey').';',
        'boxed' => ' border: '.$css->line(1, 'solid', 'grey').';',
        default => '',
    };
    $soft = (string) $palette->background('soft');
    $grand = match ($s['grand_style']) {
        'fill' => 'background-color: '.$palette->accent.'; color: '.$palette->onAccent().'; padding: 7px 8px;',
        'soft' => 'background-color: '.$soft.'; color: '.$palette->text('accent', $soft).'; padding: 6px 8px;',
        'double' => 'border-top: 3px double '.$palette->accent.'; border-bottom: 3px double '.$palette->accent.'; color: '.$accentInk.'; padding: 6px 5px;',
        'box' => 'border: 1.5px solid '.$palette->accent.'; color: '.$accentInk.'; padding: 6px 8px;',
        'plain' => 'color: '.$ink.'; padding: 6px 5px;',
        default => 'border-top: 1.5px solid '.$palette->accent.'; color: '.$accentInk.'; padding: 6px 5px;',
    };
    $grand .= ' font-size: '.$px($s['grand_size']).'; font-weight: bold;';
    $side = $width < 100 ? ($s['position'] === 'center' ? (100 - $width) / 2 : 100 - $width) : 0;
@endphp
<table>
    <tr>
        @if($side > 0 && $s['position'] !== 'left')<td style="width: {{ $side }}%;"></td>@endif
        <td style="width: {{ $width }}%;">
            <table>
                @foreach($view['totals'] as $row)
                    @if($row['style'] === 'grand')
                        <tr>
                            <td style="{{ $grand }}">{{ $row['label'] }}</td>
                            <td style="{{ $grand }} text-align: right; white-space: nowrap;">{{ $row['value'] }}</td>
                        </tr>
                    @else
                        @php $strong = $row['style'] === 'strong' ? ' font-weight: bold;' : ''; @endphp
                        <tr>
                            <td style="padding: {{ $padding }};{{ $rowLine }} color: {{ $strong ? $ink : $labelColor }};{{ $strong }}">{{ $row['label'] }}</td>
                            <td style="padding: {{ $padding }};{{ $rowLine }} text-align: right; white-space: nowrap;{{ $strong }}">{{ $row['value'] }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
            @if($s['show_tax_note'] && $view['tax_note'])
                <div style="margin-top: 4px; text-align: right; font-size: {{ $px($page->scale(8)) }}; color: {{ $palette->text('muted', $bg) }};">{{ $view['tax_note'] }}</div>
            @endif
        </td>
        @if($side > 0 && $s['position'] !== 'right')<td style="width: {{ $side }}%;"></td>@endif
    </tr>
</table>
