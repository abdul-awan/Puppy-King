{{--
    Renders any template made of rows and elements (the ready-made ones and those made with the builder).
    Every style value is normalised (fixed lists, numbers, #rrggbb colours); all text is escaped.
--}}
@php
    /** @var \App\Support\Documents\Templates\TemplateDefinition $definition */
    $page = $definition->page;
    $palette = $page->palette();
    $css = new \App\Support\Documents\Templates\Css($palette);
    $px = fn ($value) => \App\Support\Documents\Templates\Css::px($value);
    [$marginTop, $marginRight, $marginBottom, $marginLeft] = $page->pageMargins();
    // The small labels ("BILL TO", "AMOUNT IN WORDS", ...) on a background.
    $labelStyle = fn (?string $background) => 'font-size: '.$px($page->scale(7.5)).'; text-transform: uppercase; letter-spacing: '.$px($page->labelSpacing)
        .'; color: '.$palette->text($page->labelColor, $background).';';
    $footerPadding = match ($page->footerAlign) {
        'center' => '3px 90px 0 90px',
        'right' => '3px 0 0 90px',
        default => '3px 90px 0 0',
    };
    $footerBorder = match ($page->footerBorder) {
        'accent' => 'border-top: 1.5px solid '.$palette->accent.';',
        'line' => 'border-top: 1px solid #e5e7eb;',
        default => '',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $document->number }}</title>
    <style>
        @page { margin: {{ $marginTop }}px {{ $marginRight }}px {{ $marginBottom }}px {{ $marginLeft }}px; }
        body { font-family: {!! $page->fontFamily() !!}; font-size: {{ $px($page->baseFontSize()) }}; color: {{ $page->text }}; line-height: 1.38; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; text-align: left; }
        img { border: 0; }
        .pre { white-space: pre-line; }
        .footer { position: fixed; bottom: -{{ max(10, $marginBottom - 16) }}px; left: 0; right: 0; height: 12px; font-size: {{ $px($page->scale(7)) }}; color: #9ca3af; text-align: {{ $page->footerAlign }}; padding: {{ $footerPadding }}; {{ $footerBorder }} }
        .deco { position: fixed; background-color: {{ $palette->accent }}; }
    </style>
</head>
<body>
@switch($page->decoration)
    @case('top_band')
        <div class="deco" style="top: -{{ $marginTop }}px; left: -{{ $marginLeft }}px; right: -{{ $marginRight }}px; height: 9px;"></div>
        @break
    @case('top_line')
        <div class="deco" style="top: -{{ $marginTop }}px; left: -{{ $marginLeft }}px; right: -{{ $marginRight }}px; height: 4px;"></div>
        @break
    @case('bottom_band')
        <div class="deco" style="bottom: -{{ $marginBottom }}px; left: -{{ $marginLeft }}px; right: -{{ $marginRight }}px; height: 9px;"></div>
        @break
    @case('left_stripe')
        <div class="deco" style="top: -{{ $marginTop }}px; left: -{{ $marginLeft }}px; width: 8px; height: 2000px;"></div>
        @break
@endswitch

@if($footer)
    <div class="footer">{{ $footer }}</div>
@endif

@foreach($definition->rows as $row)
    @php
        $visible = array_map(fn (array $cell) => array_values(array_filter($cell['elements'], fn (array $element) => $context->visible($element))), $row['cells']);
    @endphp
    @if(array_merge(...$visible) !== [])
        @php
            $rowBox = $css->box($row['style'], null);
            $widths = \App\Support\Documents\Templates\TemplateDefinition::LAYOUTS[$row['layout']];
            $gap = (float) $row['style']['gap'];
            $last = count($row['cells']) - 1;
        @endphp
        <table style="{{ $rowBox['css'] }}{{ $row['style']['keep_together'] ? '; page-break-inside: avoid' : '' }}">
            <tr>
                @foreach($row['cells'] as $index => $cell)
                    @php $cellBox = $css->box($cell['style'], $rowBox['background']); @endphp
                    <td style="width: {{ $widths[$index] }}%; vertical-align: {{ $cell['style']['valign'] }}; padding: 0 {{ $index < $last ? $px($gap / 2) : '0px' }} 0 {{ $index > 0 ? $px($gap / 2) : '0px' }};">
                        <div style="{{ $cellBox['css'] }}">
                            @foreach($visible[$index] as $element)
                                @php
                                    $fit = $element['type'] === 'title' && $element['settings']['fit'];
                                    $box = $css->box($fit ? \Illuminate\Support\Arr::only($element['style'], ['align', 'margin_top']) : $element['style'], $cellBox['background']);
                                    $inner = $fit ? $css->box(\Illuminate\Support\Arr::except($element['style'], ['align', 'margin_top']), $cellBox['background']) : null;
                                @endphp
                                <div class="el-{{ $element['type'] }}" style="{{ $box['css'] }}">
                                    @include('pdf.builder.elements.'.$element['type'], [
                                        'element' => $element,
                                        's' => $element['settings'],
                                        'bg' => $inner['background'] ?? $box['background'],
                                        'ink' => $inner['ink'] ?? $box['ink'],
                                        'inner' => $inner,
                                    ])
                                </div>
                            @endforeach
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
@endforeach
</body>
</html>
