{{-- The title of the document; with "fit" its background is only behind the text (a label). --}}
@if($inner)
    <span style="display: inline-block; {{ $inner['css'] }}">{{ $title }}</span>
@else
    {{ $title }}
@endif
