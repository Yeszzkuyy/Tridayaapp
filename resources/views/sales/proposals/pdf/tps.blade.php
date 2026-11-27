<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->proposal_number }}</title>
    @php
        $forPdf = $forPdf ?? false;
        $previewMode = $previewMode ?? false;
        $img = fn (?string $path) => $path ? ($forPdf ? public_path($path) : asset($path)) : null;
        $money = fn ($value) => number_format((float) $value, 0, ',', '.');
        $qtyText = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        $sales = $sales ?? ($proposal->lead?->assignee ?: $proposal->creator);
        $salesName = $sales?->name ?: ($company['signatory']['name'] ?? '-');
        $salesEmail = $sales?->email ?: ($company['email'] ?? '');
        $salesPhone = $sales?->phone ?: ($company['contact_phone'] ?? '');
        $signaturePath = $sales && $sales->signature_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->path($sales->signature_path)
            : null;
        if ($signaturePath && ! is_file($signaturePath)) {
            $signaturePath = null;
        }
        if ($signaturePath && ! $forPdf) {
            $signaturePath = asset('storage/'.$sales->signature_path);
        }
        $taxPercent = $proposal->tax_rate !== null
            ? (float) $proposal->tax_rate
            : ((float) $proposal->subtotal > 0 ? round((float) $proposal->tax / (float) $proposal->subtotal * 100) : 0);
        $deliveryTime = $proposal->delivery_terms ?: '-';
        $terms = $company['terms'] ?? [];
        $bank = $company['bank'] ?? [];
    @endphp
    <style>
        @font-face { font-family: "LibSans"; font-style: normal; font-weight: normal; src: url("file://{{ resource_path('fonts/LiberationSans-Regular.ttf') }}") format("truetype"); }
        @font-face { font-family: "LibSans"; font-style: normal; font-weight: bold; src: url("file://{{ resource_path('fonts/LiberationSans-Bold.ttf') }}") format("truetype"); }
        @font-face { font-family: "LibSans"; font-style: italic; font-weight: normal; src: url("file://{{ resource_path('fonts/LiberationSans-Italic.ttf') }}") format("truetype"); }
        @page { size: A4; margin: 0; }
        body { margin: 0; font-family: "LibSans", "DejaVu Sans", sans-serif; color: #000; font-size: 5.8pt; line-height: 1.35; }
        .sheet { padding: 54pt 58pt 38pt 58pt; background: #fff; }
        table { border-collapse: collapse; width: 100%; }
        .banner { width: 478pt; margin-left: 0.5pt; display: block; }
        .head { margin-top: 0; }
        .head td { vertical-align: top; }
        .doc-title { font-size: 16.8pt; font-weight: bold; padding-top: 22pt; letter-spacing: .2pt; }
        .company-wrap { text-align: right; padding-top: 4pt; }
        .company-name { font-size: 7pt; font-weight: bold; }
        .company-meta { font-size: 4.7pt; line-height: 1.3; margin-top: 1pt; }
        .company-logo { width: 48pt; padding-left: 8pt; }
        .info { margin-top: 6pt; }
        .info td { vertical-align: top; padding: .9pt 0; }
        .info-left { width: 73.5%; padding-right: 8pt; }
        .info-right { width: 26.5%; }
        .label { font-weight: bold; width: 68pt; }
        .sep { width: 7pt; }
        .right-label { font-weight: bold; width: 62pt; }
        .right-value { background: #f3f3f3; padding-left: 3pt; }
        .items { margin-top: 13pt; }
        .items th { background: #29468f; color: #fff; font-size: 5.8pt; font-weight: bold; text-align: center; padding: 4pt 2pt; border: .6pt solid #000; line-height: 1.18; }
        .items td { font-size: 5.8pt; padding: 3pt 2pt; border: .6pt solid #000; vertical-align: top; line-height: 1.3; }
        .items .no { text-align: center; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .center { text-align: center; }
        .items .product { font-weight: bold; }
        .items .desc-title { font-weight: bold; }
        .totals { width: 143pt; margin-left: auto; margin-top: 4pt; font-size: 5.8pt; }
        .totals td { padding: 1.2pt 3pt; line-height: 1.2; }
        .totals .label { text-align: right; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .first td { border-top: .8pt solid #000; }
        .totals .grand td { font-weight: bold; }
        .terms { margin-top: 15pt; }
        .terms-title { font-size: 5.8pt; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .terms-list { margin: 3pt 0 0 12pt; padding: 0; font-size: 5.8pt; }
        .terms-list li { margin: 0 0 0.8pt; }
        .notes { margin-top: 8pt; font-size: 5.8pt; }
        .bank-box { background: #f3f3f3; width: 260pt; margin-top: 11pt; padding: 4pt 8pt; font-size: 5.8pt; line-height: 1.25; }
        .bank-box .title, .bank-box .bank-name { font-weight: bold; }
        .contact { margin-top: 12pt; font-size: 5.8pt; line-height: 1.35; }
        .sign-block { margin-top: 13pt; margin-left: 18pt; width: 190pt; }
        .sign-date { font-size: 5.8pt; margin-bottom: 2pt; }
        .sign-block img { max-width: 120pt; max-height: 58pt; }
        .sign-space { height: 50pt; }
        .sign-name { font-size: 5.8pt; font-weight: bold; margin-top: 4pt; }
        .sign-phone { font-size: 5.8pt; margin-top: 1pt; }
        .preview-toolbar { position: fixed; top: 12px; right: 12px; z-index: 99; }
        .preview-toolbar button { border: 0; border-radius: 6px; background: #0f766e; color: #fff; font-size: 14px; padding: 9px 16px; cursor: pointer; }
        @media print { .preview-toolbar { display: none; } }
    </style>
    @if($previewMode)
        <style>
            body { background: #e5e7eb; padding: 18px 0; }
            .sheet { margin: 0 auto; box-shadow: 0 12px 34px rgba(15, 23, 42, .18); }
        </style>
    @endif
</head>
<body>
    @if($previewMode)
        <div class="preview-toolbar"><button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button></div>
    @endif

    <div class="sheet">
        @if(!empty($company['banner']))
            <img class="banner" src="{{ $img($company['banner']) }}" alt="">
        @endif

        <table class="head">
            <tr>
                <td style="width: 55%;">
                    <div class="doc-title">QUOTATION</div>
                </td>
                <td style="width: 45%;">
                    <table>
                        <tr>
                            <td class="company-wrap">
                                <div class="company-name">{{ $company['name'] }}</div>
                                <div class="company-meta">
                                    @foreach($company['address_lines'] ?? [] as $line)
                                        {{ $line }}<br>
                                    @endforeach
                                    Telp. {{ $company['phone'] ?? '-' }}
                                </div>
                            </td>
                            <td style="width: 56pt;">
                                @if(!empty($company['logo']))
                                    <img class="company-logo" src="{{ $img($company['logo']) }}" alt="{{ $company['name'] }}">
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="info">
            <tr>
                <td class="info-left">
                    <table>
                        <tr><td class="label">Company's Name</td><td class="sep">:</td><td><strong>{{ $proposal->customer ?: '-' }}</strong></td></tr>
                        <tr><td class="label">Address</td><td class="sep">:</td><td>{{ $proposal->customer_address ?: '-' }}</td></tr>
                        <tr><td class="label">Contact</td><td class="sep">:</td><td>{{ $proposal->contact_name ?: '-' }}</td></tr>
                        <tr><td class="label">Phone</td><td class="sep">:</td><td>{{ $proposal->contact_phone ?: '-' }}</td></tr>
                        <tr><td class="label">E-mail</td><td class="sep">:</td><td>{{ $proposal->contact_email ?: '-' }}</td></tr>
                        <tr><td class="label">Subject</td><td class="sep">:</td><td><strong>{{ $proposal->project_description ?: '-' }}</strong></td></tr>
                    </table>
                </td>
                <td class="info-right">
                    <table>
                        <tr><td class="right-label">Date</td><td class="sep">:</td><td class="right-value">{{ $proposal->proposal_date?->format('d F Y') ?? '-' }}</td></tr>
                        <tr><td class="right-label">No. Quotation</td><td class="sep">:</td><td class="right-value">{{ $proposal->proposal_number }}</td></tr>
                        <tr><td class="right-label">Delivery Time</td><td class="sep">:</td><td class="right-value">{{ $deliveryTime }}</td></tr>
                        <tr><td class="right-label">Payment</td><td class="sep">:</td><td class="right-value">{{ $proposal->payment_terms_text ?: $proposal->payment_terms ?: '-' }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 4%;">No</th>
                    <th style="width: 18.3%;">Item Name</th>
                    <th style="width: 36.7%;">Description</th>
                    <th style="width: 9.1%;">Estimated<br>Lead Time</th>
                    <th style="width: 3.1%;">Qty</th>
                    <th style="width: 12.5%;">Pricelist</th>
                    <th style="width: 12.5%;">Total Price</th>
                </tr>
            </thead>
            <tbody>
                @forelse($proposal->items as $index => $item)
                    @php
                        $lines = preg_split('/\r\n|\r|\n/', (string) $item->description) ?: [];
                        $title = trim((string) array_shift($lines));
                        $leadTime = $item->lead_time ?: ($item->stock_status
                            ? ($item->stock_status === 'indent' ? 'Indent'.($item->stock_eta ? ' '.$item->stock_eta : '') : ($item->stock_status === 'limited' ? 'Ready Limited' : 'Ready Stock'))
                            : '-');
                        $productName = $item->scope_of_supply ?: ($item->product?->sku ?: ($item->product?->part_number ?: '-'));
                    @endphp
                    <tr>
                        <td class="no">{{ $index + 1 }}</td>
                        <td class="product">{{ $productName }}</td>
                        <td>
                            @if($title !== '')
                                <div class="desc-title">{{ $title }}</div>
                            @endif
                            @foreach($lines as $line)
                                {{ $line }}<br>
                            @endforeach
                        </td>
                        <td>{{ $leadTime }}</td>
                        <td class="center">{{ $qtyText($item->quantity) }}</td>
                        <td class="num">{{ $money($item->unit_price) }}</td>
                        <td class="num">{{ $money($item->subtotal) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="center">-</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr class="first">
                <td class="label">Total Price</td>
                <td class="value">{{ $money($proposal->subtotal) }}</td>
            </tr>
            @if((float) $proposal->discount > 0)
                <tr>
                    <td class="label">Discount</td>
                    <td class="value">{{ $money($proposal->discount) }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">VAT{{ $taxPercent > 0 ? ' '.$taxPercent.'%' : '' }}</td>
                <td class="value">{{ $money($proposal->tax) }}</td>
            </tr>
            <tr class="grand">
                <td class="label">Total</td>
                <td class="value">{{ $money($proposal->grand_total) }}</td>
            </tr>
        </table>

        <div class="terms">
            <div class="terms-title">Terms and Conditions</div>
            <ol class="terms-list">
                @foreach($terms as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ol>
        </div>

        @if(filled($proposal->notes))
            <div class="notes"><strong>Notes:</strong> {!! nl2br(e($proposal->notes)) !!}</div>
        @endif

        @if(!empty($bank))
            <div class="bank-box">
                <div class="title">Bank Account</div>
                <div class="bank-name">{{ $bank['name'] ?? '' }}</div>
                <div>a/n {{ $bank['holder'] ?? '' }}</div>
                <div>No. {{ $bank['account'] ?? '' }}</div>
            </div>
        @endif

        <div class="contact">
            Thank you for your attention and cooperation.<br>
            If have any questions or require further information, feel free to contact by phone or e-mail : {{ $salesEmail ?: '-' }}
        </div>

        <div class="sign-block">
            <div class="sign-date">Jakarta, {{ $proposal->proposal_date?->format('d F Y') ?? '-' }}</div>
            @if($signaturePath)
                <img src="{{ $signaturePath }}" alt="{{ $salesName }}">
            @else
                <div class="sign-space"></div>
            @endif
            <div class="sign-name">{{ $salesName }}</div>
            @if($salesPhone)
                <div class="sign-phone">{{ $salesPhone }}</div>
            @endif
        </div>
    </div>
</body>
</html>
