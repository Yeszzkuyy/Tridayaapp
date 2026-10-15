<?php

return [
    'firecrawl' => [
        'key' => env('FIRECRAWL_API_KEY'),
        'base_url' => env('FIRECRAWL_BASE_URL', 'https://api.firecrawl.dev'),
        'timeout' => (int) env('FIRECRAWL_TIMEOUT', 120),
        'wait_for' => (int) env('FIRECRAWL_WAIT_FOR', 3000),
        // Batas aman crawl katalog agar tak menyedot seluruh website.
        'crawl_limit' => (int) env('FIRECRAWL_CRAWL_LIMIT', 10),
        // Markdown lebih pendek dari ini dianggap halaman listing -> crawl.
        'listing_threshold' => (int) env('FIRECRAWL_LISTING_THRESHOLD', 500),
    ],

    'import' => [
        // Maks URL per batch import.
        'max_urls' => (int) env('KB_IMPORT_MAX_URLS', 50),
        // Ukuran potongan dokumen untuk pencarian.
        'chunk_size' => (int) env('KB_CHUNK_SIZE', 1500),
        'chunk_overlap' => (int) env('KB_CHUNK_OVERLAP', 150),
    ],
];
