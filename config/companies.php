<?php

/**
 * Data kop quotation per PT.
 *
 * Logo disimpan di public/images/letterheads agar bisa dibaca Dompdf.
 * NTI/MGK/TPS: alamat, telepon, terms, rekening, dan penandatangan masih
 * menunggu data resmi; template akan menampilkan bagian yang tersedia saja.
 */
return [
    'WANI' => [
        'code' => 'WANI',
        'name' => 'PT Wiskira Adinata Indonesia',
        'logo' => 'images/letterheads/wani/logo.png',
        'accent' => '#f47b20',
        'accent_soft' => '#fde7d2',
        'address_lines' => [
            'Menara Cakrawala Lt 12, Unit 1205A',
            'Jl. M.H. Thamrin No. 9, Kebon Sirih,',
            'Menteng, Jakarta Pusat, Provinsi DKI Jakarta, 10340',
            'INDONESIA',
        ],
        'phone' => '+62 21 80637120',
        'sales_phone' => '+62 812807907',
        'email' => 'felisia@ptwani.com',
        'contact_phone' => '+628112950024',
        'terms' => [
            'Prices are quoted in IDR and valid for one (1) week.',
            'Prices include VAT, loco Jakarta.',
            'A 50% cancellation fee applies for any confirmed PO cancellation.',
            'Delivery time is 3-5 days after payment receipt.',
            'Prices are subject to change without prior notice.',
            'Payment by bank transfer to Bank Central Asia account No. 7571118860, PT Wiskira Adinata Indonesia.',
        ],
        'bank' => [
            'name' => 'Bank Central Asia (BCA)',
            'account' => '7571118860',
            'holder' => 'PT Wiskira Adinata Indonesia',
        ],
        'signatory' => [
            'name' => 'Adelina Felisia S.S.',
            'title' => 'Channel Manager',
        ],
    ],

    'NTI' => [
        'code' => 'NTI',
        'name' => 'PT Nusantara Tridaya Inovasi',
        'logo' => 'images/letterheads/nti/logo.jpg',
        'accent' => '#e11d48',
        'accent_soft' => '#ffe4e6',
        'address_lines' => [
            'Ruko CBD Palm A-28, Palm Paradise',
            'Jl. Boulevard Taman Surya 5, Pegadungan',
            'Kalideres, Jakarta Barat 11830',
            'DKI Jakarta, INDONESIA',
        ],
        'phone' => '+62 21 80637101, 80637102',
        'sales_phone' => '',
        'email' => '',
        'contact_phone' => '',
        'terms' => [],
        'bank' => [],
        'signatory' => [],
    ],

    'MGK' => [
        'code' => 'MGK',
        'name' => 'PT Mega Gada Kencana',
        'logo' => 'images/letterheads/mgk/logo.png',
        'accent' => '#4338ca',
        'accent_soft' => '#e0e7ff',
        'address_lines' => [
            'Permata Regency D/7, Kembangan',
            'Jakarta Barat, DKI Jakarta 11630',
            'INDONESIA',
        ],
        'phone' => '+62 21 8063 7111',
        'sales_phone' => '',
        'email' => '',
        'contact_phone' => '',
        'terms' => [],
        'bank' => [],
        'signatory' => [],
    ],

    'TPS' => [
        'code' => 'TPS',
        'name' => 'PT Tridaya Piranti Selaras',
        'logo' => 'images/letterheads/tps/logo.png',
        'accent' => '#dc2626',
        'accent_soft' => '#fee2e2',
        'address_lines' => [
            'Belleza BSA, 1st Floor Unit 106',
            'Jl. Letjen Soepono, Kebayoran Lama',
            'Jakarta Selatan, DKI Jakarta 1120',
            'INDONESIA',
        ],
        'phone' => '+62 21 80637190',
        'sales_phone' => '',
        'email' => '',
        'contact_phone' => '',
        'terms' => [],
        'bank' => [],
        'signatory' => [],
    ],
];
