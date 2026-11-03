{{-- Extra details of the document (e.g. the reason of a credit note). --}}
@if($s['heading_style'] === 'bar')
    @php $barBackground = $palette->background($s['bar_background']) ?? (string) $palette->background('soft'); @endphp
    @if($s['show_heading'])
        <div style="background-color: {{ $barBackground }}; color: {{ $palette->text('accent', $barBackground) }}; font-size: {{ $px($page->scale(7.5)) }}; font-weight: bold; text-transform: uppercase; letter-spacing: {{ $px($page->labelSpacing) }}; padding: 4px 8px;">{{ $sideBox['label'] }}</div>
    @endif
    <div style="padding: 7px 8px;">
        @foreach($sideBox['lines'] as $line)
            <div class="pre">{{ $line }}</div>
        @endforeach
    </div>
@else
    @if($s['show_heading'])
        <div style="{{ $labelStyle($bg) }}">{{ $sideBox['label'] }}</div>
    @endif
    @foreach($sideBox['lines'] as $line)
        <div class="pre">{{ $line }}</div>
    @endforeach
@endif
