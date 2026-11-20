<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan global aplikasi (tarif PPN, default margin quotation).
 * Nilai disimpan sebagai teks; helper di bawah mengubahnya ke angka.
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value !== null ? (string) $value : $default;
    }

    public static function set(string $key, null|string|int|float $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value],
        );
    }

    public static function vatRate(): float
    {
        $rate = (float) self::get('vat_rate', '12');

        return max(0, min(100, $rate));
    }

    public static function defaultMarginPercent(): float
    {
        $margin = (float) self::get('default_margin_percent', '20');

        return max(0, min(99.99, $margin));
    }
}
