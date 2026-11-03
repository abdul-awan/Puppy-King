{{-- The subject of the document (its own title) and its introduction. --}}
@php
    $subject = $s['show_title'] ? $document->getAttribute('title') : null;
    $introduction = $s['show_introduction'] ? $document->getAttribute('introduction') : null;
@endphp
@if(filled($subject))
    <div style="font-size: {{ $px($s['title_size']) }}; font-weight: bold;">{{ $subject }}</div>
@endif
@if(filled($introduction))
    <div class="pre" style="{{ filled($subject) ? 'margin-top: 6px;' : '' }}">{{ $introduction }}</div>
@endif
