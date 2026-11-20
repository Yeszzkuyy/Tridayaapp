<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Support\Pricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan update harga modal banyak produk sebagai satu batch.
 *
 * Setiap perubahan membuat baris riwayat baru. Nilai stok dan garansi
 * mengikuti riwayat terbaru karena keduanya tidak diedit pada layar bulk.
 */
class BulkProductPriceUpdateService
{
    /**
     * @param  array<int, array{
     *     product_id: int|string,
     *     supplier_id: int|string,
     *     price: int|float|string,
     *     price_includes_tax?: bool|int|string|null,
     *     valid_until?: string|null
     * }>  $items
     */
    public function update(array $items, User $user): int
    {
        return DB::transaction(function () use ($items, $user): int {
            $productIds = collect($items)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();

            // Lock products in stable ID order so simultaneous bulk updates
            // cannot overwrite one another midway through the batch.
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $vatRate = Pricing::vatRate();
            $updatedCount = 0;
            $priceService = app(ProductPriceService::class);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $product = $products->get($productId);

                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$productId}.product_id" => __('The selected product is no longer available.'),
                    ]);
                }

                $latestCost = $product->costLogs()->lockForUpdate()->first();

                if (! $latestCost) {
                    throw ValidationException::withMessages([
                        "items.{$productId}.product_id" => __('This product no longer has price history and cannot be bulk updated.'),
                    ]);
                }

                $priceEntered = (float) $item['price'];
                $includesTax = (bool) ($item['price_includes_tax'] ?? false);
                $netPrice = Pricing::netCost($priceEntered, $includesTax, $vatRate);
                $supplierId = (int) $item['supplier_id'];
                $validUntil = $item['valid_until'] ?? null;
                $currentValidUntil = $latestCost->valid_until?->toDateString();

                $unchanged = abs((float) $latestCost->price - $netPrice) < 0.005
                    && (int) $latestCost->supplier_id === $supplierId
                    && (string) ($currentValidUntil ?? '') === (string) ($validUntil ?? '');

                if ($unchanged) {
                    continue;
                }

                $priceService->recordCost($product, [
                    'supplier_id' => $supplierId,
                    'price' => $netPrice,
                    'price_entered' => $priceEntered,
                    'price_includes_tax' => $includesTax,
                    'tax_rate' => $vatRate,
                    'stock_status' => $latestCost->stock_status ?: 'ready',
                    'stock_eta' => $latestCost->stock_eta,
                    'warranty_info' => $latestCost->warranty_info ?: '',
                    'valid_until' => $validUntil,
                    'notes' => __('Bulk price update'),
                ], $user->id);

                $updatedCount++;
            }

            return $updatedCount;
        });
    }
}
