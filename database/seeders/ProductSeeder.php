<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /** Katalog awal: barang & jasa yang paling sering masuk BOQ. */
    public function run(): void
    {
        $rows = [
            // CCTV & keamanan
            ['CCTV-4CH', 'Paket CCTV 4 Channel + Instalasi', 'barang', 'lot', 8500000],
            ['CCTV-8CH', 'Paket CCTV 8 Channel + Instalasi', 'barang', 'lot', 15000000],
            ['CCTV-16CH', 'Paket CCTV 16 Channel + Instalasi', 'barang', 'lot', 28000000],
            ['CAM-IP', 'IP Camera 4MP Outdoor', 'barang', 'unit', 1250000],
            ['CAM-ANLG', 'Kamera Analog 2MP Indoor', 'barang', 'unit', 450000],
            ['NVR-8', 'NVR 8 Channel', 'barang', 'unit', 2200000],
            ['HDD-4TB', 'Harddisk Surveillance 4TB', 'barang', 'unit', 1800000],
            // Jaringan
            ['SW-24P', 'Switch Managed 24 Port Gigabit', 'barang', 'unit', 4500000],
            ['AP-CEIL', 'Access Point Ceiling Dual Band', 'barang', 'unit', 1600000],
            ['KBL-UTP', 'Kabel UTP Cat6 + Instalasi', 'barang', 'meter', 25000],
            ['KBL-FO', 'Kabel Fiber Optic Dropcore + Splicing', 'barang', 'meter', 35000],
            // Telepon
            ['IP-PHN', 'IP Phone Entry Level', 'barang', 'unit', 1350000],
            ['PABX-IP', 'IP PABX 32 Extension', 'barang', 'unit', 18000000],
            // Jasa
            ['JASA-SRV', 'Jasa Survey & Desain Sistem', 'jasa', 'lot', 1500000],
            ['JASA-INST', 'Jasa Instalasi per Titik', 'jasa', 'titik', 250000],
            ['JASA-TRN', 'Jasa Training & Serah Terima', 'jasa', 'lot', 1000000],
        ];

        foreach ($rows as [$sku, $name, $category, $unit, $price]) {
            Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'category' => $category,
                    'unit' => $unit,
                    'default_price' => $price,
                    // approved: tidak masuk antrean review KB, tidak ter-publish
                    // ke AI, tapi tetap bisa dipilih di picker BOQ.
                    'status' => Product::STATUS_APPROVED,
                    'is_active' => true,
                ],
            );
        }
    }
}
