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
    @endphp
    <style>
        @font-face { font-family: "LibSans"; font-style: normal; font-weight: normal; src: url("file://{{ resource_path('fonts/LiberationSans-Regular.ttf') }}") format("truetype"); }
        @font-face { font-family: "LibSans"; font-style: normal; font-weight: bold; src: url("file://{{ resource_path('fonts/LiberationSans-Bold.ttf') }}") format("truetype"); }
        @font-face { font-family: "LibSans"; font-style: italic; font-weight: normal; src: url("file://{{ resource_path('fonts/LiberationSans-Italic.ttf') }}") format("truetype"); }
        @page { size: A4; margin: 0; }
        body { margin: 0; font-family: "LibSans", "DejaVu Sans", sans-serif; color: #000; font-size: 5.4pt; line-height: 1.35; }
        .sheet { padding: 54pt 50pt 38pt; background: #fff; }
        table { border-collapse: collapse; width: 100%; }
        .head { margin-top: 2pt; }
        .head td { vertical-align: top; }
        .logo { width: 58pt; }
        .company { padding-left: 5pt; padding-top: 4pt; font-size: 4.3pt; line-height: 1.35; }
        .company-name { font-size: 6.5pt; font-weight: bold; margin-bottom: 1pt; }
        .doc-title { text-align: right; font-size: 15.7pt; font-weight: bold; letter-spacing: .3pt; padding-top: 4pt; padding-right: 30pt; }
        .doc-number { text-align: right; font-size: 7.6pt; margin-top: 2pt; padding-right: 30pt; }
        .info-box { background: #f3f3f3; margin-top: 10pt; padding: 7pt 20pt; }
        .info-box td { vertical-align: top; padding: 1.2pt 0; }
        .info-left { width: 74%; padding-right: 10pt; }
        .info-right { width: 26%; }
        .label { font-weight: bold; width: 45pt; }
        .sep { width: 7pt; }
        .right-label { font-weight: bold; width: 58pt; }
        .project-row { margin-top: 8pt; }
        .project-row td { vertical-align: middle; }
        .project-label { width: 68pt; font-weight: bold; font-size: 5.4pt; padding-left: 20pt; }
        .project-value { background: #dff1f6; padding: 3.2pt 6pt; font-weight: bold; font-size: 5.4pt; }
        .items { margin-top: 8pt; }
        .items th { background: #3733cd; color: #fff; font-size: 5.4pt; font-weight: bold; text-align: center; padding: 4pt 2pt; border: .6pt solid #000; line-height: 1.18; }
        .items td { font-size: 5.4pt; padding: 3pt 2pt; border: .6pt solid #000; vertical-align: top; line-height: 1.28; }
        .items .no { text-align: center; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .center { text-align: center; }
        .items .product { font-weight: bold; }
        .items .desc-title { font-weight: bold; }
        .totals { width: 143pt; margin-left: auto; margin-top: 3pt; font-size: 5.4pt; }
        .totals td { padding: 1.2pt 3pt; line-height: 1.2; }
        .totals .label { text-align: right; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .first td { border-top: .8pt solid #000; }
        .totals .grand td { font-weight: bold; }
        .terms-box { margin-top: 12pt; margin-left: 7.5pt; width: 424pt; background: #f3f3f3; padding: 6pt 20pt 8pt; }
        .terms-title { font-weight: bold; font-size: 5.4pt; margin-bottom: 1pt; }
        .terms-list { margin: 0 0 0 10pt; padding: 0; font-size: 5.4pt; }
        .terms-list li { margin: 0 0 1.4pt; }
        .notes { margin-top: 6pt; font-size: 5.3pt; width: 464pt; }
        .contact-lines { margin-top: 11pt; font-size: 5.4pt; line-height: 1.35; }
        .sign-block { margin-top: 12pt; margin-left: 18pt; width: 190pt; }
        .sign-date { font-size: 5.4pt; margin-bottom: 2pt; }
        .sign-block img { max-width: 130pt; max-height: 72pt; }
        .sign-space { height: 62pt; }
        .sign-name { font-size: 5.4pt; font-weight: bold; margin-top: 4pt; }
        .footer-banner { width: 464pt; margin-left: 7.5pt; margin-top: 8pt; display: block; }
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
        <table class="head">
            <tr>
                <td style="width: 60pt;">
                    @if(!empty($company['logo']))
                        <img class="logo" src="{{ $img($company['logo']) }}" alt="{{ $company['name'] }}">
                    @endif
                </td>
                <td class="company">
                    <div class="company-name">{{ $company['name'] }}</div>
                    @foreach($company['address_lines'] ?? [] as $line)
                        {{ $line }}<br>
                    @endforeach
                    <strong>E-mail</strong>&nbsp;&nbsp; : {{ $company['email'] ?? '-' }}<br>
                    <strong>Contact</strong> : {{ $company['phone'] ?? '-' }}
                </td>
                <td style="width: 160pt;">
                    <div class="doc-title">QUOTATION</div>
                    <div class="doc-number">No. {{ $proposal->proposal_number }}</div>
                </td>
            </tr>
        </table>

        <table class="info-box">
            <tr>
                <td class="info-left">
                    <table>
                        <tr><td class="label">Organization</td><td class="sep">:</td><td><strong>{{ $proposal->customer ?: '-' }}</strong></td></tr>
                        <tr><td class="label">Address</td><td class="sep">:</td><td>{{ $proposal->customer_address ?: '-' }}</td></tr>
                        <tr><td class="label">Contact</td><td class="sep">:</td><td>{{ $proposal->contact_name ?: '-' }}</td></tr>
                        <tr><td class="label">Email</td><td class="sep">:</td><td>{{ $proposal->contact_email ?: '-' }}</td></tr>
                        <tr><td class="label">Phone</td><td class="sep">:</td><td>{{ $proposal->contact_phone ?: '-' }}</td></tr>
                    </table>
                </td>
                <td class="info-right">
                    <table>
                        <tr><td class="right-label">Date</td><td class="sep">:</td><td>{{ $proposal->proposal_date?->format('d F Y') ?? '-' }}</td></tr>
                        <tr><td class="right-label">Delivery Time</td><td class="sep">:</td><td>{{ $deliveryTime }}</td></tr>
                        <tr><td class="right-label">Payment</td><td class="sep">:</td><td>{{ $proposal->payment_terms_text ?: $proposal->payment_terms ?: '-' }}</td></tr>
                        <tr><td class="right-label">Currency</td><td class="sep">:</td><td>IDR</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="project-row">
            <tr>
                <td class="project-label">Project Description</td>
                <td class="project-value">: {{ $proposal->project_description ?: '-' }}</td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 3.7%;">No</th>
                    <th style="width: 10.5%;">Product Details</th>
                    <th style="width: 43.1%;">Description</th>
                    <th style="width: 8.5%;">Estimated<br>Lead Time</th>
                    <th style="width: 3.4%;">Qty</th>
                    <th style="width: 4.4%;">Unit</th>
                    <th style="width: 11.6%;">Price</th>
                    <th style="width: 11.7%;">Total Price</th>
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
                        <td class="center">{{ strtoupper((string) ($item->unit ?: '-')) }}</td>
                        <td class="num">{{ $money($item->unit_price) }}</td>
                        <td class="num">{{ $money($item->subtotal) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="center">-</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr class="first">
                <td class="label">Total Unit</td>
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

        <div class="terms-box">
            <div class="terms-title">Terms &amp; Conditions</div>
            <ol class="terms-list">
                @foreach($terms as $term)
                    <li>{!! str_replace('10 million', '<u>10 million</u>', e($term)) !!}</li>
                @endforeach
            </ol>
        </div>

        @if(filled($proposal->notes))
            <div class="notes"><strong>Notes:</strong> {!! nl2br(e($proposal->notes)) !!}</div>
        @endif

        <div class="contact-lines">
            For order confirmation, please sign the agreement or Purchase Order and send it to us by email at {{ $salesEmail ?: '-' }}<br>
            And for further assistance, please contact us at {{ $salesPhone ?: '-' }}
        </div>

        <div class="sign-block">
            <div class="sign-date">Jakarta, {{ $proposal->proposal_date?->format('d F Y') ?? '-' }}</div>
            @if($signaturePath)
                <img src="{{ $signaturePath }}" alt="{{ $salesName }}">
            @else
                <div class="sign-space"></div>
            @endif
            <div class="sign-name">{{ $salesName }}</div>
        </div>

        @if(!empty($company['banner']))
            <img class="footer-banner" src="{{ $img($company['banner']) }}" alt="">
        @endif
    </div>
</body>
</html>
