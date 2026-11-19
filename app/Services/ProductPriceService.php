<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPriceLog;

/**
 * Pencatatan harga modal produk.
 *
 * Dipakai oleh Update Price satuan dan Bulk Price Update agar aturan
 * riwayat + sinkronisasi cost_price selalu sama.
 */
class ProductPriceService
{
    /**
     * Catat satu harga modal baru dan sinkronkan cost_price produk.
     * Pemanggil bertanggung jawab membungkus dalam transaksi bila perlu.
     */
    public function recordCost(Product $product, array $data, ?int $userId = null): ProductPriceLog
    {
        $log = ProductPriceLog::create([
            'product_id' => $product->getKey(),
            'supplier_id' => $data['supplier_id'],
            'price' => $data['price'],
            'price_type' => 'cost',
            'currency' => 'IDR',
            'stock_status' => $data['stock_status'] ?? 'ready',
            'stock_eta' => $data['stock_eta'] ?? null,
            'warranty_info' => $data['warranty_info'] ?? '',
            'valid_until' => $data['valid_until'] ?? null,
            'price_request_id' => $data['price_request_id'] ?? null,
            'estimator_request_item_id' => $data['estimator_request_item_id'] ?? null,
            'updated_by' => $userId,
            'notes' => $data['notes'] ?? null,
        ]);

        $product->update(['cost_price' => (float) $data['price']]);

        return $log;
    }
}
