<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->proposal_number }}</title>
    <style>
        body { font-family: sans-serif; color: #111; margin: 2rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: .9rem; }
        th, td { border: 1px solid #ccc; padding: .45rem .6rem; text-align: left; }
        th { background: #f3f4f6; }
        td.num, th.num { text-align: right; }
        .totals { width: 16rem; margin-left: auto; font-size: .9rem; }
        .totals p { display: flex; justify-content: space-between; margin: .2rem 0; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1>{{ $proposal->proposal_number }}</h1>
    <p>{{ $proposal->customer }} • {{ $proposal->proposal_date?->format('d M Y') ?? '-' }}</p>
    @if($proposal->valid_until)
        <p>{{ __('Berlaku sampai') }}: {{ $proposal->valid_until->format('d M Y') }}</p>
    @endif
    <table>
        <thead>
            <tr><th>{{ __('Deskripsi') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Harga') }}</th><th class="num">{{ __('Subtotal') }}</th></tr>
        </thead>
        <tbody>
            @foreach($proposal->items as $item)
                <tr>
                    <td>{{ $item->description }} ({{ $item->unit }})</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="totals">
        <p><span>{{ __('Subtotal') }}</span><span>{{ number_format($proposal->subtotal, 0, ',', '.') }}</span></p>
        <p><span>{{ __('Diskon') }}</span><span>{{ number_format($proposal->discount, 0, ',', '.') }}</span></p>
        <p><span>{{ __('Pajak') }}</span><span>{{ number_format($proposal->tax, 0, ',', '.') }}</span></p>
        <p><strong>{{ __('Grand Total') }}</strong><strong>Rp {{ number_format($proposal->grand_total, 0, ',', '.') }}</strong></p>
    </div>
    @if($proposal->notes)
        <p>{{ $proposal->notes }}</p>
    @endif
</body>
</html>
