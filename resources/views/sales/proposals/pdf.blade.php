<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->proposal_number }}</title>
    @php
        $accent = $company['accent'] ?? '#f47b20';
        $accentSoft = $company['accent_soft'] ?? '#fde7d2';
    @endphp
    <style>
        @page { margin: 26px 32px 30px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; color: #1f2937; font-size: 9px; line-height: 1.35; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .top-bar { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .top-bar td { height: 8px; padding: 0; }
        .top-bar .orange { width: 72%; background: {{ $accent }}; }
        .top-bar .dark { width: 28%; background: #3f3f46; }
        .header td { vertical-align: top; }
        .logo { max-height: 52px; max-width: 150px; }
        .company-name { font-size: 12px; font-weight: bold; color: #111827; }
        .company-meta { font-size: 8px; color: #4b5563; line-height: 1.45; }
        .title { font-size: 22px; font-weight: bold; letter-spacing: .5px; margin: 8px 0 0; color: #111827; }
        .quote-no { font-size: 9px; color: #374151; margin-top: 2px; }
        .section-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .6px; color: #111827; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .partner-table td { vertical-align: top; }
        .data-table td { padding: 2px 0; vertical-align: top; font-size: 9px; }
        .data-table .label { width: 62px; color: #374151; }
        .data-table .sep { width: 8px; color: #374151; }
        .project-box { border: 1px solid #e5e7eb; margin-bottom: 8px; }
        .project-box .head { background: {{ $accent }}; color: #fff; font-weight: bold; font-size: 8px; text-transform: uppercase; padding: 4px 6px; }
        .project-box .body { padding: 5px 6px; font-size: 9px; color: #111827; }
        .items { margin-top: 12px; }
        .items th { background: {{ $accent }}; color: #fff; font-size: 8px; font-weight: bold; text-align: left; padding: 5px 4px; border: 1px solid #e5e7eb; }
        .items td { padding: 5px 4px; border: 1px solid #e5e7eb; vertical-align: top; font-size: 8.5px; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .center { text-align: center; }
        .items .section-row td { background: {{ $accentSoft }}; font-weight: bold; color: #111827; font-size: 8.5px; padding: 4px 5px; }
        .totals { width: 42%; margin-left: 58%; margin-top: 6px; }
        .totals td { padding: 3px 5px; font-size: 9px; border: 1px solid #e5e7eb; }
        .totals .label { text-align: right; background: #f9fafb; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .grand { background: {{ $accent }}; color: #fff; font-weight: bold; }
        .totals .grand td { background: {{ $accent }}; color: #fff; font-weight: bold; }
        .terms { margin-top: 16px; font-size: 8px; color: #374151; }
        .terms ol { margin: 4px 0 0 14px; padding: 0; }
        .terms li { margin-bottom: 2px; }
        .signature { margin-top: 16px; width: 100%; }
        .signature td { vertical-align: bottom; }
        .signature .line { border-top: 1px solid #111827; width: 170px; padding-top: 4px; font-size: 9px; }
        .footer { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #4b5563; }
    </style>
</head>
<body>
    {{-- Aksen atas: oranye + gelap, mendekati kop WANI. --}}
    <table class="top-bar">
        <tr>
            <td class="orange"></td>
            <td class="dark"></td>
        </tr>
    </table>

    <table class="header">
        <tr>
            <td style="width: 55%;">
                @if(!empty($company['logo']) && file_exists(public_path($company['logo'])))
                    <img src="{{ public_path($company['logo']) }}" class="logo" alt="{{ $company['name'] }}">
                @endif
                <div class="company-name" style="margin-top: 5px;">{{ $company['name'] }}</div>
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="company-meta">
                    @foreach($company['address_lines'] ?? [] as $line)
                        {{ $line }}<br>
                    @endforeach
                    @if(!empty($company['phone']))
                        <strong>Telp</strong> : {{ $company['phone'] }}<br>
                    @endif
                    @if(!empty($company['sales_phone']))
                        <strong>Sales</strong> : {{ $company['sales_phone'] }}<br>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="title">QUOTATION</div>
    <div class="quote-no">No. : {{ $proposal->proposal_number }}</div>

    <table class="partner-table" style="margin-top: 12px;">
        <tr>
            <td style="width: 55%; padding-right: 14px;">
                <div class="section-title">Partner's Data</div>
                <table class="data-table">
                    <tr>
                        <td class="label">Organization</td><td class="sep">:</td>
                        <td>{{ $proposal->customer ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Address</td><td class="sep">:</td>
                        <td>{{ $proposal->customer_address ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Contact</td><td class="sep">:</td>
                        <td>{{ $proposal->contact_name ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">E-mail</td><td class="sep">:</td>
                        <td>{{ $proposal->contact_email ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Phone</td><td class="sep">:</td>
                        <td>{{ $proposal->contact_phone ?: '-' }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 45%;">
                <div class="project-box">
                    <div class="head">Project Description</div>
                    <div class="body">{{ $proposal->project_description ?: ($proposal->notes ?: '-') }}</div>
                </div>
                <table class="data-table">
                    <tr>
                        <td class="label">Payment</td><td class="sep">:</td>
                        <td>{{ $proposal->payment_terms_text ?: $proposal->payment_terms ?: '-' }}</td>
                    </tr>
                    @if($proposal->delivery_terms)
                        <tr>
                            <td class="label">Delivery</td><td class="sep">:</td>
                            <td>{{ $proposal->delivery_terms }}</td>
                        </tr>
                    @endif
                    @if($proposal->warranty_terms)
                        <tr>
                            <td class="label">Warranty</td><td class="sep">:</td>
                            <td>{{ $proposal->warranty_terms }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="label">Date</td><td class="sep">:</td>
                        <td>{{ $proposal->proposal_date?->format('d F Y') ?? '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @php
        $groups = $proposal->items->groupBy(fn ($item) => $item->section ?: 'MATERIAL');
        $rowNo = 0;
        $vatPercent = (float) $proposal->subtotal > 0
            ? round(((float) $proposal->tax / (float) $proposal->subtotal) * 100)
            : 0;
    @endphp

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;" class="center">No</th>
                <th style="width: 12%;">Scope of Supply</th>
                <th style="width: 38%;">Description</th>
                <th style="width: 11%;">Estimated Lead Time</th>
                <th style="width: 8%;" class="num">Qty</th>
                <th style="width: 7%;">Unit</th>
                <th style="width: 10%;" class="num">Pricelist</th>
                <th style="width: 10%;" class="num">Total Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $section => $items)
                <tr class="section-row">
                    <td colspan="8">{{ $section }}</td>
                </tr>
                @foreach($items as $item)
                    @php $rowNo++; @endphp
                    <tr>
                        <td class="center">{{ $rowNo }}</td>
                        <td>{{ $item->scope_of_supply ?: ($item->product?->sku ?: '-') }}</td>
                        <td>{!! nl2br(e($item->description)) !!}
                            @if($item->stock_status || $item->warranty_info)
                                <div class="muted" style="font-size: 7.5px; margin-top: 2px;">
                                    @if($item->stock_status)
                                        Stock: {{ $item->stock_status === 'indent' ? 'Indent' : 'Ready' }}{{ $item->stock_status === 'indent' && $item->stock_eta ? ' '.$item->stock_eta : '' }}
                                    @endif
                                    @if($item->warranty_info)
                                        @if($item->stock_status) · @endif Warranty: {{ $item->warranty_info }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>{{ $item->lead_time ?: '-' }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="num">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Total Unit</td>
            <td class="value">{{ number_format((float) $proposal->subtotal, 0, ',', '.') }}</td>
        </tr>
        @if((float) $proposal->discount > 0)
            <tr>
                <td class="label">Discount</td>
                <td class="value">{{ number_format((float) $proposal->discount, 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Value Added Tax{{ $vatPercent > 0 ? ' '.$vatPercent.'%' : '' }}</td>
            <td class="value">{{ number_format((float) $proposal->tax, 0, ',', '.') }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Total</td>
            <td class="value">{{ number_format((float) $proposal->grand_total, 0, ',', '.') }}</td>
        </tr>
    </table>

    @php
        $bankAccount = $company['bank']['account'] ?? null;
        $generalTerms = collect($company['terms'] ?? [])
            ->reject(fn ($term) => $bankAccount && str_contains($term, $bankAccount))
            ->values();
    @endphp

    @if($generalTerms->isNotEmpty() || !empty($company['bank']))
        <div class="terms">
            <div class="section-title">General Terms and Conditions</div>
            @if($generalTerms->isNotEmpty())
                <ol>
                    @foreach($generalTerms as $term)
                        <li>{{ $term }}</li>
                    @endforeach
                </ol>
            @endif
            @if(!empty($company['bank']))
                <p style="margin: 4px 0 0;">
                    Payment by bank transfer to
                    <strong>{{ $company['bank']['name'] ?? '' }}</strong>
                    account No. <strong>{{ $company['bank']['account'] ?? '' }}</strong>,
                    {{ $company['bank']['holder'] ?? '' }}.
                </p>
            @endif
        </div>
    @endif

    <table class="signature">
        <tr>
            <td style="width: 55%;">
                @if(!empty($company['email']) || !empty($company['contact_phone']))
                    <div class="muted" style="font-size: 8px;">
                        Kindly submit the signed Agreement or Purchase Order to
                        {{ $company['email'] ?? '-' }} to confirm your order.<br>
                        Should you require additional support, please reach us at
                        {{ $company['contact_phone'] ?? '-' }}.
                    </div>
                @endif
            </td>
            <td style="width: 45%; text-align: right;">
                @if(!empty($company['signatory']['name']))
                    <div style="height: 42px;"></div>
                    <div class="line" style="margin-left: auto;">
                        <strong>{{ $company['signatory']['name'] }}</strong><br>
                        <span class="muted">{{ $company['signatory']['title'] ?? '' }}</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $company['name'] }}
        @if(!empty($company['email'])) · {{ $company['email'] }} @endif
        @if(!empty($company['contact_phone'])) · {{ $company['contact_phone'] }} @endif
    </div>
</body>
</html>
