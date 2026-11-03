{{-- The company's name, address, contact details and tax numbers. --}}
@php
    $nameStyle = 'font-size: '.$px($s['name_size']).'; color: '.$palette->text($s['name_color'], $bg).'; font-weight: '.($s['name_bold'] ? 'bold' : 'normal').';'
        .($s['name_uppercase'] ? ' text-transform: uppercase;' : '')
        .($s['name_letter_spacing'] > 0 ? ' letter-spacing: '.$px($s['name_letter_spacing']).';' : '');
    $parts = [
        'address' => [$s['address'], ', '],
        'contact' => [$s['contact'], '  ·  '],
        'tax' => [$s['tax_numbers'], '  ·  '],
    ];
@endphp
@if(filled($s['heading']))
    <div style="{{ $labelStyle($bg) }} margin-bottom: 2px;">{{ $s['heading'] }}</div>
@endif
@if($s['show_name'] && filled($seller['name']))
    <div style="{{ $nameStyle }}">{{ $seller['name'] }}</div>
@endif
@foreach($parts as $part => [$mode, $glue])
    @if($mode === 'stacked')
        @foreach($seller[$part] as $line)
            <div>{{ $line }}</div>
        @endforeach
    @elseif($mode === 'inline' && $seller[$part] !== [])
        <div>{{ implode($glue, $seller[$part]) }}</div>
    @endif
@endforeach
