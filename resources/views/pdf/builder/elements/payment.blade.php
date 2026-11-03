{{-- How to pay: the bank details of the company profile. --}}
@if($s['show_label'])
    <div style="{{ $labelStyle($bg) }}">{{ filled($s['label']) ? $s['label'] : $payment['label'] }}</div>
@endif
@foreach($payment['lines'] as $line)
    <div>{{ $line }}</div>
@endforeach
