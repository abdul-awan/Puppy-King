{{-- A QR code that opens the document online. --}}
@php
    $size = (int) $s['size'];
    $caption = filled($s['caption']) ? $s['caption'] : 'Scan to open this '.mb_strtolower($title).' online.';
@endphp
@if($s['caption_position'] === 'right')
    <table>
        <tr>
            <td style="width: {{ $size + 10 }}px; vertical-align: middle;"><img src="{{ $qr }}" alt="" style="width: {{ $size }}px; height: {{ $size }}px;"></td>
            <td style="vertical-align: middle; color: {{ $palette->text('muted', $bg) }};">{{ $caption }}</td>
        </tr>
    </table>
@else
    <img src="{{ $qr }}" alt="" style="width: {{ $size }}px; height: {{ $size }}px;">
    @if($s['caption_position'] === 'below')
        <div style="margin-top: 2px; color: {{ $palette->text('muted', $bg) }};">{{ $caption }}</div>
    @endif
@endif
