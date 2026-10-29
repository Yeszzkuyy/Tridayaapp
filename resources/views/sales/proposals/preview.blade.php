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
        .letterhead { border-bottom: 3px solid #0f766e; padding-bottom: .8rem; margin-bottom: 1.2rem; }
        .letterhead h2 { margin: 0; font-size: 1.35rem; letter-spacing: .04em; }
        .letterhead p { margin: .25rem 0 0; color: #475569; font-size: .85rem; }
        .print-actions { text-align: right; margin-bottom: 1rem; }
        .print-actions button { background: #0f766e; color: #fff; border: 0; border-radius: .5rem; padding: .55rem .9rem; cursor: pointer; }
        @media print { body { margin: 0; } .print-actions { display: none; } }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </div>
    <div class="letterhead">
        <h2>3DY GROUP</h2>
        <p>{{ __('Quotation') }}</p>
    </div>
    <h1>{{ $proposal->proposal_number }}</h1>
    <p>{{ $proposal->customer }} • {{ $proposal->proposal_date?->format('d M Y') ?? '-' }}</p>
    @if($proposal->lead->assignee)
        <p>{{ __('Sales Owner') }}: {{ $proposal->lead->assignee->name }}</p>
    @endif
    @if($proposal->creator)
        <p>{{ __('Prepared By') }}: {{ $proposal->creator->name }}</p>
    @endif
    @if($proposal->valid_until)
        <p>{{ __('Valid until') }}: {{ $proposal->valid_until->format('d M Y') }}</p>
    @endif
    <p><strong>{{ __('Payment Terms') }}:</strong> {{ $proposal->paymentTermsSummary() }}</p>
    @if($proposal->delivery_terms)
        <p><strong>{{ __('Delivery Terms') }}:</strong> {{ $proposal->delivery_terms }}</p>
    @endif
    <table>
        <thead>
            <tr><th>{{ __('Description') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Price') }}</th><th class="num">{{ __('Subtotal') }}</th></tr>
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
        <p><span>{{ __('Discount') }}</span><span>{{ number_format($proposal->discount, 0, ',', '.') }}</span></p>
        <p><span>{{ __('Tax') }}</span><span>{{ number_format($proposal->tax, 0, ',', '.') }}</span></p>
        <p><strong>{{ __('Grand Total') }}</strong><strong>Rp {{ number_format($proposal->grand_total, 0, ',', '.') }}</strong></p>
    </div>
    @if($proposal->notes)
        <p><strong>{{ __('Notes') }}:</strong> {{ $proposal->notes }}</p>
    @endif
</body>
</html>
