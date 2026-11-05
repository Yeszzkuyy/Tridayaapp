<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; color: #111; margin: 2rem; font-size: 13px; }
        h1 { font-size: 1.4rem; margin: 0; }
        .letterhead { border-bottom: 3px solid #0f766e; padding-bottom: .8rem; margin-bottom: 1.2rem; display: flex; justify-content: space-between; gap: 1rem; }
        .letterhead h2 { margin: 0; font-size: 1.25rem; letter-spacing: .04em; }
        .muted { color: #64748b; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { border: 1px solid #cbd5e1; padding: .45rem .6rem; text-align: left; }
        th { background: #f1f5f9; }
        .num { text-align: right; }
        .totals { width: 18rem; margin-left: auto; }
        .totals p { display: flex; justify-content: space-between; margin: .2rem 0; }
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
        <div>
            <h2>3DY GROUP</h2>
            <p class="muted">{{ __('Invoice') }}</p>
        </div>
        <div style="text-align:right">
            <h1>{{ $invoice->invoice_number }}</h1>
            <p class="muted">{{ $invoice->issue_date?->format('d M Y') }}</p>
        </div>
    </div>

    <div class="grid">
        <div>
            <p class="muted">{{ __('Bill To') }}</p>
            <p><strong>{{ $invoice->customer?->name }}</strong></p>
            <p>{!! nl2br(e($invoice->billing_address ?: $invoice->customer?->billingAddress())) !!}</p>
            @if($invoice->customer?->npwp)
                <p>{{ __('NPWP') }}: {{ $invoice->customer->npwp }}</p>
            @endif
        </div>
        <div>
            <p><span class="muted">{{ __('Customer PO Number') }}:</span> {{ $invoice->customer_po_number ?: '-' }}</p>
            <p><span class="muted">{{ __('Payment Terms') }}:</span> {{ $invoice->payment_terms ?: '-' }}</p>
            <p><span class="muted">{{ __('Due Date') }}:</span> {{ $invoice->due_date?->format('d M Y') ?? '-' }}</p>
            <p><span class="muted">{{ __('Project') }}:</span> {{ $invoice->project?->project_name ?? '-' }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th>{{ __('Unit') }}</th>
                <th class="num">{{ __('Price') }}</th>
                <th class="num">{{ __('Subtotal') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td>{{ $item->unit }}</td>
                    <td class="num">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">{{ __('Invoice amount') }}</td>
                    <td class="num">{{ number_format($invoice->amount, 0, ',', '.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        @if($invoice->items->isNotEmpty())
            <p><span>{{ __('Subtotal') }}</span><span>{{ number_format($invoice->subtotal, 0, ',', '.') }}</span></p>
            <p><span>{{ __('Discount') }}</span><span>{{ number_format($invoice->discount, 0, ',', '.') }}</span></p>
            <p><span>{{ __('Tax') }}</span><span>{{ number_format($invoice->tax, 0, ',', '.') }}</span></p>
        @endif
        <p><strong>{{ __('Total') }}</strong><strong>Rp {{ number_format($invoice->amount, 0, ',', '.') }}</strong></p>
        <p><span>{{ __('Paid') }}</span><span>Rp {{ number_format($invoice->paidAmount(), 0, ',', '.') }}</span></p>
        <p><span>{{ __('Remaining') }}</span><span>Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</span></p>
    </div>

    @if($invoice->notes)
        <p style="margin-top:1.5rem"><strong>{{ __('Notes') }}:</strong> {!! nl2br(e($invoice->notes)) !!}</p>
    @endif
</body>
</html>
