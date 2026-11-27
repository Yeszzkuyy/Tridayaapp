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
        body { margin: 0; font-family: "LibSans", "DejaVu Sans", sans-serif; color: #000; font-size: 5.9pt; line-height: 1.35; }
        .sheet { padding: 54pt 18pt 38pt; background: #fff; }
        table { border-collapse: collapse; width: 100%; }
        .top { position: relative; height: 42pt; }
        .top .banner { position: absolute; left: 0; top: 4.6pt; width: 559pt; }
        .top .logo { position: absolute; left: 11pt; top: 0; width: 56pt; }
        .band { background: #f3f3f3; margin-top: 3pt; padding: 5pt 10pt; }
        .band td { vertical-align: top; }
        .doc-title { font-size: 17.2pt; font-weight: bold; letter-spacing: .2pt; }
        .doc-number { font-size: 8.3pt; margin-top: 1pt; }
        .company-meta { text-align: right; font-size: 4.7pt; line-height: 1.2; padding-top: 1pt; }
        .partner { margin-top: 9pt; }
        .partner-heading { font-size: 5.9pt; font-weight: bold; margin-bottom: 1pt; }
        .partner td { vertical-align: top; padding: .8pt 0; }
        .partner-left { width: 72%; padding-right: 10pt; }
        .partner-right { width: 28%; }
        .label { font-weight: bold; width: 74pt; }
        .sep { width: 7pt; }
        .project-head { background: #ff914d; color: #fff; font-size: 5.9pt; font-weight: bold; text-transform: uppercase; padding: 2.5pt 5pt; }
        .project-body { background: #fff7f1; text-align: center; font-weight: bold; font-size: 5.9pt; padding: 7pt 4pt; }
        .pay-line { font-weight: bold; font-size: 5.9pt; padding-top: 1.5pt; }
        .items { margin-top: 13pt; }
        .items th { background: #ff914d; color: #fff; font-size: 5.9pt; font-weight: bold; text-align: center; padding: 4pt 2pt; border: .6pt solid #000; line-height: 1.18; }
        .items td { font-size: 5.9pt; padding: 3pt 2pt; border: .6pt solid #000; vertical-align: top; line-height: 1.3; }
        .items .no { text-align: center; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .center { text-align: center; }
        .items .product { font-weight: bold; }
        .items .desc-title { font-weight: bold; }
        .items .warranty { color: #e30613; font-weight: bold; margin-top: 3pt; }
        .totals { width: 143pt; margin-left: auto; margin-top: 4pt; font-size: 5.9pt; }
        .totals td { padding: 1.2pt 3pt; line-height: 1.2; }
        .totals .label { text-align: right; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .first td { border-top: .8pt solid #000; }
        .totals .grand td { font-weight: bold; }
        .terms { margin-top: 21pt; }
        .terms-title { font-size: 5.9pt; font-weight: bold; text-transform: uppercase; margin-bottom: 1pt; }
        .terms-list { margin: 0 0 0 12pt; padding: 0; font-size: 5.9pt; }
        .terms-list li { margin: 0 0 1.6pt; }
        .notes { margin-top: 7pt; font-size: 5.9pt; }
        .bottom { margin-top: 10pt; }
        .bottom td { vertical-align: bottom; }
        .contact { font-size: 5.9pt; line-height: 1.35; }
        .signature { width: 160pt; margin-left: auto; text-align: center; }
        .signature img { max-width: 92pt; max-height: 52pt; }
        .signature .space { height: 46pt; }
        .signature .line { border-top: .8pt solid #000; padding-top: 3pt; font-size: 5.9pt; font-weight: bold; width: 100pt; margin: 0 auto; }
        .signature .role { font-size: 5.9pt; margin-top: 1pt; }
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
        <div class="top">
            @if(!empty($company['banner']))
                <img class="banner" src="{{ $img($company['banner']) }}" alt="">
            @endif
            @if(!empty($company['logo']))
                <img class="logo" src="{{ $img($company['logo']) }}" alt="{{ $company['name'] }}">
            @endif
        </div>

        <table class="band">
            <tr>
                <td style="width: 62%;">
                    <div class="doc-title">QUOTATION</div>
                    <div class="doc-number">No. : {{ $proposal->proposal_number }}</div>
                </td>
                <td style="width: 38%;" class="company-meta">
                    @foreach($company['address_lines'] ?? [] as $line)
                        {{ $line }}<br>
                    @endforeach
                    Telp&nbsp;&nbsp;&nbsp;&nbsp; : {{ $company['phone'] ?? '-' }}<br>
                    Sales&nbsp;&nbsp; : {{ $salesPhone ?: '-' }}
                </td>
            </tr>
        </table>

        <table class="partner">
            <tr>
                <td class="partner-left">
                    <div class="partner-heading">PARTNER'S DATA</div>
                    <table>
                        <tr><td class="label">Organization</td><td class="sep">:</td><td><strong>{{ $proposal->customer ?: '-' }}</strong></td></tr>
                        <tr><td class="label">Address</td><td class="sep">:</td><td>{{ $proposal->customer_address ?: '-' }}</td></tr>
                        <tr><td class="label">Contact</td><td class="sep">:</td><td>{{ $proposal->contact_name ?: '-' }}</td></tr>
                        <tr><td class="label">E-mail</td><td class="sep">:</td><td>{{ $proposal->contact_email ?: '-' }}</td></tr>
                        <tr><td class="label">Phone</td><td class="sep">:</td><td>{{ $proposal->contact_phone ?: '-' }}</td></tr>
                    </table>
                </td>
                <td class="partner-right">
                    <div class="project-head">Project Description</div>
                    <div class="project-body">{{ $proposal->project_description ?: '-' }}</div>
                    <div class="pay-line">Payment : {{ $proposal->payment_terms_text ?: $proposal->payment_terms ?: '-' }}</div>
                    <div class="pay-line">Date&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : {{ $proposal->proposal_date?->format('d F Y') ?? '-' }}</div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 3.6%;">No</th>
                    <th style="width: 19.1%;">Scope of Supply</th>
                    <th style="width: 38%;">Description</th>
                    <th style="width: 8.3%;">Estimated<br>Lead Time</th>
                    <th style="width: 2.8%;">Qty</th>
                    <th style="width: 11.4%;">Pricelist</th>
                    <th style="width: 3.6%;">Disc</th>
                    <th style="width: 11.4%;">Total Price</th>
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
                        $gross = (float) $item->quantity * (float) $item->unit_price;
                        $discountPercent = $gross > 0 ? round((float) $item->discount / $gross * 100) : 0;
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
                            @if(filled($item->warranty_info))
                                <div class="warranty">{{ $item->warranty_info }}</div>
                            @endif
                        </td>
                        <td>{{ $leadTime }}</td>
                        <td class="center">{{ $qtyText($item->quantity) }}</td>
                        <td class="num">{{ $money($item->unit_price) }}</td>
                        <td class="center">{{ $discountPercent > 0 ? $discountPercent.'%' : '-' }}</td>
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
                <td class="label">Value Added Tax{{ $taxPercent > 0 ? ' '.$taxPercent.'%' : '' }}</td>
                <td class="value">{{ $money($proposal->tax) }}</td>
            </tr>
            <tr class="grand">
                <td class="label">Total</td>
                <td class="value">{{ $money($proposal->grand_total) }}</td>
            </tr>
        </table>

        <div class="terms">
            <div class="terms-title">General Terms and Conditions</div>
            <ol class="terms-list">
                @foreach($terms as $term)
                    <li>
                        @if(str_contains($term, "Bank Central Asia's"))
                            {!! nl2br(str_replace(
                                ["Bank Central Asia's", (string) ($company['bank']['account'] ?? '')],
                                ["<strong>Bank Central Asia's</strong>", '<strong>'.e($company['bank']['account'] ?? '').'</strong>'],
                                e($term)
                            )) !!}
                        @else
                            {{ $term }}
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>

        @if(filled($proposal->notes))
            <div class="notes"><strong>Notes:</strong> {!! nl2br(e($proposal->notes)) !!}</div>
        @endif

        <table class="bottom">
            <tr>
                <td style="width: 62%; padding-right: 18pt;">
                    <div class="contact">
                        Kindly submit the signed Agreement or Purchase Order to {{ $salesEmail ?: '-' }} to confirm your order.<br>
                        Should you require additional support, please reach us at {{ $salesPhone ?: '-' }}
                    </div>
                </td>
                <td style="width: 38%;">
                    <div class="signature">
                        @if($signaturePath)
                            <img src="{{ $signaturePath }}" alt="{{ $salesName }}">
                        @else
                            <div class="space"></div>
                        @endif
                        <div class="line">{{ $salesName }}</div>
                        @if(!empty($company['signatory']['title']))
                            <div class="role">{{ $company['signatory']['title'] }}</div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
