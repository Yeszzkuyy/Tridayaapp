<?php

namespace App\Services;

class ConversationTitle
{
    /**
     * Rapikan judul percakapan untuk ditampilkan: kupas kutip,
     * buang sisa potongan "...", potong di batas kata (max 60 huruf).
     */
    public static function smart(?string $title, int $max = 60): string
    {
        $title = preg_replace('/\s+/u', ' ', trim((string) $title)) ?? '';

        // Kupas tanda kutip/pasangan pembungkus + pungtuasi akhir + sisa
        // potongan ("..."/"…") berulang sampai stabil.
        do {
            $before = $title;
            $title = trim(preg_replace('/^[\'"«»„“”‘’「」『』‹›\(\[]+|[\'"«»„“”‘’「」『』‹›\)\]]+$/u', '', $title) ?? '');
            $title = rtrim($title, " \t\n\r\0\x0B.,!?:;");
            $title = (string) preg_replace('/(\.{3,}|…)+$/u', '', $title);
        } while ($title !== $before);
        $title = rtrim($title, " \t\n\r\0\x0B.,!?:;");

        if ($title === '') {
            return __('Percakapan baru');
        }

        if (mb_strlen($title) <= $max) {
            return $title;
        }

        $cut = mb_substr($title, 0, $max);
        $space = mb_strrpos($cut, ' ');

        return $space !== false && $space > (int) ($max * 0.4)
            ? mb_substr($cut, 0, $space)
            : $cut;
    }

    /**
     * Bersihkan spasi/karakter tak terlihat dari isi pesan untuk
     * ditampilkan. NBSP, joiner Arab, dan spasi legitim lain sengaja
     * dipertahankan (ada gunanya). Database tidak diubah.
     */
    public static function cleanContent(?string $content): string
    {
        $text = str_replace(["\u{202F}", "\u{2007}"], ' ', (string) $content);

        return (string) preg_replace('/[\x{200B}\x{FEFF}\x{00AD}\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $text);
    }

    /**
     * Bersihkan teks pesan dari pengguna sebelum dikirim/disimpan:
     * buang karakter nol-lebar di mana saja, pangkas spasi/newline
     * (termasuk unicode) di kedua ujung. Isi tengah (enter antar
     * baris, kode) tidak disentuh.
     */
    public static function cleanMessage(?string $text): string
    {
        $text = (string) preg_replace('/[\x{200B}\x{FEFF}\x{00AD}\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', (string) $text);

        return (string) preg_replace('/^[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{202F}\x{205F}\x{3000}]+|[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{202F}\x{205F}\x{3000}]+$/u', '', $text);
    }
}
