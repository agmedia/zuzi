@push('css')
    <style>
        #products, #totals {
            /*font-family: "Roboto", Arial, Helvetica, sans-serif;*/
            font-size: 13px;
            border-collapse: collapse;
            width: 100%;
        }

        #products td, #products th {
            border: 1px solid #ddd;
            padding: 8px;
        }

        #totals td, #totals th {
            border: 1px solid #ddd;
            padding: 6px 8px;
        }

        #products tr:nth-child(even){background-color: #f2f2f2;}

        /*#products tr:hover {background-color: #ddd;}*/

        #products th {
            font-size: 15px;
            padding-top: 12px;
            padding-bottom: 12px;
            text-align: left;
            background-color: #e50077;
            color: white;
        }
    </style>
@endpush

<table id="products">
    <tr>
        <th>Proizvod</th>
        <th style="text-align: center;" width="15%">Kol.</th>
        <th style="text-align: right;" width="20%">Cijena</th>
        <th style="text-align: right;" width="25%">Ukupno</th>
    </tr>
    @foreach ($order->products as $product)
        <tr>
            <td>{{ $product->name }} - {{ $product->product->sku}}</td>
            <td style="text-align: center;">{{ $product->quantity }}</td>
            <td style="text-align: right;">€ {{ number_format($product->price, 2, ',', '.') }}</td>
            <td style="text-align: right;">€ {{ number_format($product->total, 2, ',', '.') }}</td>
        </tr>
    @endforeach
</table>
<table id="totals">
    @foreach ($order->totals as $total)
        <tr>
            <td style="border-right: none; border-top: none;"></td>
            <td style="border-left: none; border-right: none;"></td>
            <td style="border-left: none; text-align: right; {{ $total->code == 'shipping' ? '' : 'font-weight: bold;' }}">{{ $total->title }}</td>
            @if ($order->shipping_state != 'Croatia' && $total->code == 'shipping')
                <td style="border-left: none; text-align: right; {{ $total->code == 'shipping' ? '' : 'font-weight: bold;' }}" width="20%">Trošak dostave će Vam biti poslan u roku od 24h.</td>
            @else
                <td style="border-left: none; text-align: right; {{ $total->code == 'shipping' ? '' : 'font-weight: bold;' }}" width="20%">€ {{ number_format($total->value, 2, ',', '.') }}</td>
            @endif
        </tr>
    @endforeach
</table>

<p style="text-align: right;font-size: 10px;"> PDV uključen u cijenu. Od toga
    @foreach ($order->totals as $total)
        @if($total->code == 'subtotal')
        €<strong>{{ number_format($total->value - ($total->value / 1.05 ), 2, ',', '.') }}</strong>  PDV knjige i
    @elseif ($total->code == 'shipping')
        €<strong>{{number_format($total->value - ($total->value / 1.25 ), 2, ',', '.') }}</strong>  PDV dostava
    @endif
    @endforeach
</p>

