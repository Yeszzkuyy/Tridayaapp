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
        body { margin: 0; font-family: "LibSans", "DejaVu Sans", sans-serif; color: #000; font-size: 5.6pt; line-height: 1.35; }
        .sheet { padding: 54pt 50pt 38pt; background: #fff; }
        img { border: 0; }
        table { border-collapse: collapse; width: 100%; }
        .banner { width: 478pt; margin-left: 8pt; display: block; }
        .head { margin-top: 2pt; }
        .head td { vertical-align: top; }
        .logo { height: 32pt; width: auto; }
        .company { padding-left: 0; padding-top: 1pt; }
        .company-name { font-size: 6.8pt; font-weight: bold; letter-spacing: .1pt; }
        .company-meta { font-size: 4.5pt; line-height: 1.3; margin-top: 1pt; }
        .doc-title { text-align: right; font-size: 16.3pt; font-weight: bold; letter-spacing: .2pt; padding-top: 12pt; padding-right: 28pt; }
        .info { margin-top: 12pt; }
        .info td { vertical-align: top; padding: .8pt 0; }
        .info .label { font-weight: bold; width: 65pt; padding-left: 9pt; }
        .info .sep { width: 7pt; }
        .info .right-label { font-weight: bold; width: 60pt; }
        .info .right-value { background: #f3f3f3; padding-left: 3pt; }
        .info-left { width: 74.5%; padding-right: 12pt; }
        .info-right { width: 25.5%; }
        .quote-bar { border: .8pt solid #000; margin-top: 10pt; padding: 4.5pt 6pt; font-size: 5.6pt; }
        .quote-bar td { padding: 0; vertical-align: middle; }
        .quote-bar .date { text-align: center; }
        .quote-bar .delivery { text-align: right; }
        .delivery-value { color: #e30613; font-weight: bold; }
        .items { margin-top: 9pt; }
        .items th { background: #2385ae; color: #fff; font-size: 5.4pt; font-weight: bold; text-align: center; padding: 4pt 2pt; border: .6pt solid #000; line-height: 1.2; }
        .items td { font-size: 5.4pt; padding: 3pt 2pt; border: .6pt solid #000; vertical-align: top; line-height: 1.3; }
        .items .no { text-align: center; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .center { text-align: center; }
        .items .product { font-weight: bold; }
        .items .desc-title { font-weight: bold; }
        .totals { width: 143pt; margin-left: auto; margin-top: 3pt; font-size: 5.5pt; }
        .totals td { padding: 1.2pt 3pt; vertical-align: middle; line-height: 1.2; }
        .totals .label { text-align: right; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .first td { border-top: .8pt solid #000; }
        .totals .grand td { font-weight: bold; }
        .terms { margin-top: 13pt; margin-left: 9pt; }
        .terms-head { background: #f4cccc; width: 238pt; font-size: 5.6pt; font-weight: bold; text-transform: uppercase; padding: 2.6pt 5pt; }
        .terms ol { margin: 4pt 0 0 12pt; padding: 0; font-size: 5.4pt; }
        .terms li { margin: 0 0 1.3pt; }
        .notes { margin-top: 6pt; font-size: 5.4pt; }
        .bottom { margin-top: 8pt; }
        .bottom td { vertical-align: bottom; }
        .bank { margin-left: 9pt; font-size: 5.4pt; line-height: 1.35; }
        .bank-title { font-weight: bold; margin-bottom: 1pt; }
        .contact { margin-top: 7pt; margin-left: 9pt; font-size: 5.3pt; line-height: 1.35; }
        .signature { width: 90pt; margin-left: auto; text-align: center; }
        .signature img { max-width: 95pt; max-height: 48pt; }
        .signature .space { height: 44pt; }
        .signature .line { border-top: .8pt solid #000; width: 80pt; margin: 0 auto; padding-top: 2.5pt; font-size: 5.6pt; font-weight: bold; }
        .signature .role { font-size: 5.4pt; margin-top: .5pt; }
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
        <img class="banner" src="{{ $img($company['banner'] ?? null) }}" alt="">

        <table class="head">
            <tr>
                <td style="width: 59pt; padding-left: 13pt;">
                    @if(!empty($company['logo']))
                        <img class="logo" src="{{ $img($company['logo']) }}" alt="{{ $company['name'] }}">
                    @endif
                </td>
                <td class="company">
                    <div class="company-name">{{ $company['name'] }}</div>
                    <div class="company-meta">
                        @foreach($company['address_lines'] ?? [] as $line)
                            {{ $line }}<br>
                        @endforeach
                        <strong>Telp</strong>&nbsp;&nbsp; : {{ $company['phone'] ?? '-' }}<br>
                        <strong>Sales</strong> : {{ $salesPhone ?: '-' }}
                    </div>
                </td>
                <td style="width: 150pt;">
                    <div class="doc-title">QUOTATION</div>
                </td>
            </tr>
        </table>

        <table class="info">
            <tr>
                <td class="info-left">
                    <table>
                        <tr><td class="label">Organization</td><td class="sep">:</td><td><strong>{{ $proposal->customer ?: '-' }}</strong></td></tr>
                        <tr><td class="label">Address</td><td class="sep">:</td><td>{{ $proposal->customer_address ?: '-' }}</td></tr>
                        <tr><td class="label">Contact</td><td class="sep">:</td><td>{{ $proposal->contact_name ?: '-' }}</td></tr>
                        <tr><td class="label">Email</td><td class="sep">:</td><td>{{ $proposal->contact_email ?: '-' }}</td></tr>
                        <tr><td class="label">Phone</td><td class="sep">:</td><td>{{ $proposal->contact_phone ?: '-' }}</td></tr>
                        <tr><td class="label">Project</td><td class="sep">:</td><td>{{ $proposal->project_description ?: '-' }}</td></tr>
                    </table>
                </td>
                <td class="info-right">
                    <table>
                        <tr><td class="right-label">Account Executive</td><td class="sep">:</td><td class="right-value">{{ $salesName }}</td></tr>
                        <tr><td class="right-label">E-mail</td><td class="sep">:</td><td class="right-value">{{ $salesEmail ?: '-' }}</td></tr>
                        <tr><td class="right-label">Phone</td><td class="sep">:</td><td class="right-value">{{ $salesPhone ?: '-' }}</td></tr>
                        <tr><td class="right-label">Payment</td><td class="sep">:</td><td class="right-value">{{ $proposal->payment_terms_text ?: $proposal->payment_terms ?: '-' }}</td></tr>
                        <tr><td class="right-label">Currency</td><td class="sep">:</td><td class="right-value">IDR</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="quote-bar">
            <tr>
                <td style="width: 45%;"><strong>No. Quotation :</strong> {{ $proposal->proposal_number }}</td>
                <td class="date" style="width: 30%;"><strong>Quotation Date :</strong> {{ $proposal->proposal_date?->format('d F Y') ?? '-' }}</td>
                <td class="delivery" style="width: 25%;"><strong>Delivery Time :</strong> <span class="delivery-value">{{ $deliveryTime }}</span></td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 3.8%;">No</th>
                    <th style="width: 17.7%;">Product's Name</th>
                    <th style="width: 34.6%;">Description</th>
                    <th style="width: 8.9%;">Estimated<br>Lead Time</th>
                    <th style="width: 3%;">Qty</th>
                    <th style="width: 4.6%;">Unit</th>
                    <th style="width: 12.1%;">Pricelist</th>
                    <th style="width: 12.1%;">Total Price</th>
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
                <td class="label">Value Added Tax</td>
                <td class="value">{{ $money($proposal->tax) }}</td>
            </tr>
            <tr class="grand">
                <td class="label">Grand Total</td>
                <td class="value">{{ $money($proposal->grand_total) }}</td>
            </tr>
        </table>

        <div class="terms">
            <div class="terms-head">General Terms and Conditions</div>
            <ol>
                @foreach($terms as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ol>
        </div>

        @if(filled($proposal->notes))
            <div class="notes"><strong>Notes:</strong> {!! nl2br(e($proposal->notes)) !!}</div>
        @endif

        <table class="bottom">
            <tr>
                <td style="width: 55%; padding-right: 18pt;">
                    @if(!empty($bank))
                        <div class="bank">
                            <div class="bank-title">Bank Account</div>
                            <strong>{{ $bank['holder'] ?? '' }}</strong><br>
                            {{ $bank['name'] ?? '' }}<br>
                            a/c. {{ $bank['account'] ?? '' }}
                        </div>
                    @endif
                    <div class="contact">
                        For Order Confirmation, please sign up on agreement/Purchase Order and email to us : {{ $salesEmail ?: '-' }}<br>
                        If you have further questions, please feel free to contact us | {{ $salesPhone ?: '-' }}
                    </div>
                </td>
                <td style="width: 45%;">
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
