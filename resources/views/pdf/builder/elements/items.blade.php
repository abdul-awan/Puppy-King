{{-- The lines of the document, with the columns of the invoice settings and the header and row style of the element. --}}
@php
    $columns = $view['columns'];
    $span = count($columns);
    $compact = $s['compact'] === 'on' || ($s['compact'] === 'auto' && $span >= 9);
    $grid = $s['row_style'] === 'grid';

    $headerBackground = match ($s['header_style']) {
        'fill' => $palette->background($s['header_background']) ?? $palette->accent,
        'light' => $palette->background($s['header_background']),
        default => null,
    };
    $headerOn = $headerBackground ?? $bg;
    $headerInk = $s['header_color'] !== 'auto'
        ? $palette->text($s['header_color'], $headerOn)
        : match ($s['header_style']) {
            'fill' => $palette->text('auto', $headerOn),
            'lines' => $palette->text('accent', $headerOn),
            'plain' => $palette->text('muted', $headerOn),
            default => $palette->text('text', $headerOn),
        };
    $headerLine = $palette->line($s['header_line']);
    $rowLine = $css->line(1, $s['line_style'], $s['row_line']);
    $gridLine = $css->line(1, 'solid', $s['row_line']);

    $padding = $compact ? '5px 3px' : '6px 5px';
    $th = 'padding: '.$padding.'; font-size: '.$px($page->scale($compact ? 6.8 : 7.5)).'; color: '.$headerInk.'; font-weight: '.($s['header_bold'] ? 'bold' : 'normal').';'
        .($s['header_uppercase'] ? ' text-transform: uppercase; letter-spacing: 0.3px;' : '')
        .($headerBackground ? ' background-color: '.$headerBackground.';' : '')
        .match ($s['header_style']) {
            'fill' => $grid ? ' border: 1px solid '.$headerBackground.';' : '',
            'light' => ' border-bottom: 1.5px solid '.$headerLine.';',
            'lines' => ' border-top: 1px solid '.$headerLine.'; border-bottom: 1px solid '.$headerLine.';',
            default => ' border-bottom: 1.5px solid '.$headerLine.';',
        }
        .($grid && $s['header_style'] !== 'fill' ? ' border-left: '.$gridLine.'; border-right: '.$gridLine.';' : '');

    $td = 'padding: '.$padding.';'.($compact ? ' font-size: '.$px($page->scale(8.5)).';' : '')
        .match ($s['row_style']) {
            'lines' => ' border-bottom: '.$rowLine.';',
            'grid' => ' border: '.$gridLine.';',
            default => '',
        };
    $stripe = $s['row_style'] === 'striped' ? $palette->background('faint') : null;
    $muted = $palette->text('muted', $bg);
    $section = match ($s['section_style']) {
        'italic' => ' font-style: italic; color: '.$palette->text('accent', $bg).';',
        'band' => ' font-weight: bold; background-color: '.$palette->background('faint').';',
        default => ' font-weight: bold;',
    };
    $number = 0;
@endphp
<table>
    <thead>
    <tr>
        @foreach($columns as $column)
            <th style="{{ $th }} text-align: {{ $column['align'] }};{{ $column['width'] ? ' width: '.$column['width'].';' : '' }}">{{ $column['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($view['rows'] as $row)
        @if($row['type'] === 'section')
            <tr><td colspan="{{ $span }}" style="{{ $td }}{{ $section }} padding-top: 9px;">{{ $row['description'] }}</td></tr>
        @elseif($row['type'] === 'text')
            <tr>
                @if($options->itemNumbers)<td style="{{ $td }}"></td>@endif
                <td colspan="{{ $options->itemNumbers ? $span - 1 : $span }}" class="pre" style="{{ $td }} font-style: italic; color: {{ $muted }};">{{ $row['description'] }}</td>
            </tr>
        @else
            @php
                $number++;
                $background = $stripe !== null && $number % 2 === 0 ? ' background-color: '.$stripe.';' : '';
            @endphp
            <tr>
                @foreach($columns as $column)
                    @if($column['key'] === 'description')
                        <td style="{{ $td }}{{ $background }}">
                            @if($row['code'])<span style="color: {{ $muted }}; font-size: {{ $px($page->scale(8)) }};">{{ $row['code'] }}</span>&nbsp; @endif{{ $row['description'] }}
                            @if($s['show_details'] && $row['details'])<div class="pre" style="color: {{ $muted }}; font-size: {{ $px($page->scale(8.5)) }}; margin-top: 2px;">{{ $row['details'] }}</div>@endif
                        </td>
                    @else
                        <td style="{{ $td }}{{ $background }} text-align: {{ $column['align'] }};{{ $column['align'] === 'right' ? ' white-space: nowrap;' : '' }}{{ $column['key'] === 'number' ? ' color: '.$muted.';' : '' }}">{{ $row['cells'][$column['key']] ?? '' }}</td>
                    @endif
                @endforeach
            </tr>
        @endif
    @endforeach
    </tbody>
</table>
