{{-- The details of the document (number, dates, ...) in one of four layouts. --}}
@php
    $labelColor = $palette->text($s['label_color'], $bg);
    $weight = $s['value_bold'] ? 'bold' : 'normal';
    $align = $element['style']['align'] ?? 'left';
@endphp
@if(filled($s['heading']))
    <div style="{{ $labelStyle($bg) }} margin-bottom: 3px;">{{ $s['heading'] }}</div>
@endif
@switch($s['layout'])
    @case('inline')
        <div>
            @foreach($view['meta'] as $row)
                <span style="color: {{ $labelColor }};">{{ $row['label'] }}</span> <span style="font-weight: {{ $weight }};">{{ $row['value'] }}</span>@if(! $loop->last)<span style="color: {{ $labelColor }};">&nbsp;&nbsp;·&nbsp;&nbsp;</span>@endif
            @endforeach
        </div>
        @break
    @case('stacked')
        @foreach($view['meta'] as $row)
            <div style="{{ $loop->first ? '' : 'margin-top: 5px;' }}">
                <div style="color: {{ $labelColor }}; font-size: {{ $px($page->scale(8)) }};">{{ $row['label'] }}</div>
                <div style="font-weight: {{ $weight }};">{{ $row['value'] }}</div>
            </div>
        @endforeach
        @break
    @case('columns')
        <table>
            <tr>
                @foreach($view['meta'] as $row)
                    <td style="text-align: {{ $align }}; padding-right: {{ $loop->last ? 0 : 12 }}px;">
                        <div style="color: {{ $labelColor }}; font-size: {{ $px($page->scale(7.5)) }}; text-transform: uppercase; letter-spacing: {{ $px($page->labelSpacing) }};">{{ $row['label'] }}</div>
                        <div style="font-weight: {{ $weight }}; color: {{ $ink }};">{{ $row['value'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
        @break
    @default
        <table>
            @foreach($view['meta'] as $row)
                <tr>
                    <td style="width: {{ $s['label_width'] }}%; color: {{ $labelColor }}; text-align: {{ $align }}; padding: 1px 8px 1px 0;">{{ $row['label'] }}</td>
                    <td style="font-weight: {{ $weight }}; color: {{ $ink }}; text-align: {{ $align }}; padding: 1px 0;">{{ $row['value'] }}</td>
                </tr>
            @endforeach
        </table>
@endswitch
