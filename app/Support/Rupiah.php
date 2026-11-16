<?php

namespace App\Support;

/**
 * Normalisasi input nominal Rupiah yang dikirim dari form berformat
 * ribuan (mis. "444.444.444") menjadi angka murni ("444444444").
 */
final class Rupiah
{
    public static function normalize(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return str_replace(['.', ' ', "\u{00A0}"], '', (string) $value);
    }
}
