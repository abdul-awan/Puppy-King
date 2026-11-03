{{-- The name, address and tax numbers of the customer. --}}
<div style="font-size: {{ $px($s['name_size']) }}; font-weight: bold; margin-top: 2px;">{{ $customer['name'] }}</div>
@if($s['show_address'] && $customer['address'])
    <div class="pre">{{ $customer['address'] }}</div>
@endif
@if($s['show_tax_numbers'])
    @foreach($customer['tax'] as $line)
        <div>{{ $line }}</div>
    @endforeach
@endif
