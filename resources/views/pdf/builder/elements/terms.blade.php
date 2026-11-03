{{-- The terms and conditions of the document. --}}
@if($s['show_label'] && filled($s['label']))
    <div style="{{ $labelStyle($bg) }}">{{ $s['label'] }}</div>
@endif
<div class="pre" style="margin-top: 2px;">{{ $view['terms'] }}</div>
