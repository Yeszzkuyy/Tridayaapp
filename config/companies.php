<?php

/**
 * Data kop quotation resmi per PT.
 *
 * Sumber: contoh PDF resmi di storage/app/template-references.
 * Logo memakai berkas di public/images/logos; banner kop di
 * public/images/letterheads/<kode>/banner.png.
 */
return [
    'WANI' => [
        'code' => 'WANI',
        'name' => 'PT Wiskira Adinata Indonesia',
        'logo' => 'images/logos/wani-logo-trim.png',
        'banner' => 'images/letterheads/wani/banner.png',
        'accent' => '#f47b20',
        'accent_soft' => '#fde7d2',
        'address_lines' => [
            'Menara Cakrawala Lt 12, Unit 1205A',
            'Jl. M.H. Thamrin No. 9, Kebon Sirih,',
            'Menteng, Jakarta Pusat, Provinsi DKI Jakarta, 10340',
            'INDONESIA',
        ],
        'phone' => '+62 21 8123456',
        'sales_phone' => '',
        'email' => 'rosa@ptwani.com',
        'contact_phone' => '+628123456789',
        'terms' => [
            'Prices are quoted in IDR and valid for one (1) week.',
            'Prices include VAT, loco Jakarta.',
            'A 50% cancellation fee applies for any confirmed PO cancellation.',
            'Delivery time is Indent 5-7 Days after payment receipt.',
            'Prices are subject to change without prior notice.',
            "Payment by bank transfer to Bank Central Asia's account No. 66666666, PT Wiskira Adinata Indonesia.",
        ],
        'bank' => [
            'name' => 'Bank Central Asia',
            'account' => '66666666',
            'holder' => 'PT Wiskira Adinata Indonesia',
        ],
        'signatory' => [
            'name' => 'Adelina Felisia S.S.',
            'title' => 'Channel Manager',
        ],
    ],

    'NTI' => [
        'code' => 'NTI',
        'name' => 'NUSANTARA TRIDAYA INOVASI, PT',
        'logo' => 'images/logos/nti-logo-trim.png',
        'banner' => 'images/letterheads/nti/banner.png',
        'accent' => '#2385ae',
        'accent_soft' => '#f4cccc',
        'address_lines' => [
            'Ruko CBD Palm A-28, Palm Paradise',
            'Jl. Boulevard Taman Surya 5, Pegadungan, Kalideres,',
            'Jakarta Barat 11830, DKI Jakarta - Indonesia',
        ],
        'phone' => '+62 21 88888888, 8888888',
        'sales_phone' => '',
        'email' => 'cici@ptnti.com',
        'contact_phone' => '+62 888-8888-888',
        'terms' => [
            'Quotation in IDR, valid for 1 (one) week',
            'Price Include shipping cost (FOB Jakarta) more than 10 Million IDR',
            '50% cancellation fees will be charge if cancel after PO',
            'Delivery time estimated lead time 3-5 Days after payment received',
            'Payment offer by due date we will be charge 2% per day',
            'Price is subject to change without prior notice',
        ],
        'bank' => [
            'name' => 'BRI - KC JAKARTA RAWAMANGUN',
            'account' => '1234-1234-1234-012',
            'holder' => 'PT NUSANTARA TRIDAYA INOVASI',
        ],
        'signatory' => [
            'name' => '',
            'title' => 'Inside Sales',
        ],
    ],

    'MGK' => [
        'code' => 'MGK',
        'name' => 'PT Mega Gada Kencana',
        'logo' => 'images/logos/mgk-logo-trim.png',
        'banner' => 'images/letterheads/mgk/banner.png',
        'accent' => '#3733cd',
        'accent_soft' => '#dff1f6',
        'address_lines' => [
            'Permata Regency D/37, Kembangan, Jakarta Barat,',
            'DKI Jakarta, Indonesia, 11630',
        ],
        'phone' => '(021) 8111 1111',
        'sales_phone' => '',
        'email' => 'info@megagada.co.id',
        'contact_phone' => '+62 877-7777-7777',
        'terms' => [
            'Quotation in IDR, valid for 1 (one) week',
            'Purchases under 10 million for Jakarta area are exclude shipping costs',
            '50% cancellation fees will be charge if cancel after PO',
            'Price is subject to change without prior notice',
            'Payment offer by due date we will be charge 0.2% per day',
            "Bank transfer to BCA's account No. 1111-1111, PT Mega Gada Kencana",
        ],
        'bank' => [
            'name' => 'Bank Central Asia (BCA)',
            'account' => '1111-1111',
            'holder' => 'PT Mega Gada Kencana',
        ],
        'signatory' => [
            'name' => '',
            'title' => '',
        ],
    ],

    'TPS' => [
        'code' => 'TPS',
        'name' => 'PT TRIDAYA PIRANTI SELARAS',
        'logo' => 'images/logos/tps-logo-trim.png',
        'banner' => 'images/letterheads/tps/banner.png',
        'accent' => '#29468f',
        'accent_soft' => '#f3f3f3',
        'address_lines' => [
            'Belleza BSA, 1st Floor Unit 106, Jl. Letjen Soepeno,',
            'Kebayoran Lama, Jakarta Selatan,',
            'DKI Jakarta, Indonesia, 12210',
        ],
        'phone' => '+62-21-80637190',
        'sales_phone' => '',
        'email' => 'info@pirantiselaras.com',
        'contact_phone' => '+62 811-1234-1234',
        'terms' => [
            'This price quotation is valid for one (1) week from the date of issuance',
            'The quoted prices exclude installation services and exclude delivery/shipping costs',
            'Prices are subject to change and product availability is based on current stock levels',
            'Any confirmed Purchase Order cancellation will be subject to a cancellation fee of 50% of the total order value.',
            'Payment shall be made via bank transfer to the following account',
        ],
        'bank' => [
            'name' => 'Bank Central Asia',
            'account' => '1234 123456',
            'holder' => 'PT TRIDAYA PIRANTI SELARAS',
        ],
        'signatory' => [
            'name' => '',
            'title' => '',
        ],
    ],
];
