<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aturan upload file yang reusable.
 *
 * - Menolak ekstensi berbahaya (script/executable) terlepas dari MIME yang diklaim.
 * - Memastikan ekstensi ada di daftar yang diizinkan.
 * - Memeriksa MIME asli dari isi file (finfo), bukan sekadar klaim client,
 *   dengan fallback untuk MIME generik (zip/octet-stream/text) yang sah untuk
 *   format Office/txt ketika ekstensinya diizinkan.
 * - Ukuran file ditangani rule `max:` per-endpoint (beda-beda).
 */
class SecureFile implements ValidationRule
{
    private const DANGEROUS_EXTENSIONS = [
        // PHP & varian server-side
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'phar', 'phps',
        // Executable / script OS
        'exe', 'msi', 'scr', 'com', 'bat', 'cmd', 'sh', 'bash', 'csh', 'ksh', 'zsh',
        'vbs', 'vbe', 'js', 'jsp', 'jspx', 'asp', 'aspx', 'asa', 'asmx', 'cgi', 'pl',
        'pm', 'py', 'pyc', 'rb', 'jar', 'apk', 'dll', 'so', 'bin', 'reg', 'ps1',
        // Config / lainnya
        'htaccess', 'htpasswd', 'shtml', 'svgz',
    ];

    public function __construct(
        private readonly array $allowedExtensions = [],
        private readonly array $allowedMimes = [],
    ) {}

    public function validate($attribute, $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('File tidak valid atau gagal diunggah.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        // 1. Tolak ekstensi berbahaya apa pun, termasuk yang di-rename.
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            $fail('Jenis file tidak diizinkan.');

            return;
        }

        // 2. Ekstensi harus dalam daftar yang diizinkan (jika disediakan).
        if ($this->allowedExtensions && ! in_array($extension, $this->allowedExtensions, true)) {
            $fail('Jenis file tidak diizinkan.');

            return;
        }

        // 3. MIME asli dari isi file harus sesuai (jika disediakan).
        if ($this->allowedMimes) {
            $mime = $value->getMimeType();
            $mimeOk = in_array($mime, $this->allowedMimes, true);

            // Fallback: file generik yang sah untuk format Office/txt (mis. finfo
            // membaca xlsx/docx sebagai application/zip, atau file kosong sebagai
            // octet-stream) — hanya diterima bila ekstensinya juga diizinkan.
            $genericOk = in_array($mime, ['application/zip', 'application/octet-stream', 'text/plain'], true)
                && in_array($extension, $this->allowedExtensions, true);

            if (! $mimeOk && ! $genericOk) {
                $fail('Isi file tidak sesuai dengan tipe yang diizinkan.');

                return;
            }
        }
    }

    public static function images(): self
    {
        return new self(
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        );
    }

    public static function documents(): self
    {
        return new self(
            ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'csv'],
            [
                'application/pdf',
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/x-rar-compressed',
                'application/vnd.rar',
            ],
        );
    }

    public static function spreadsheets(): self
    {
        return new self(
            ['csv', 'txt', 'xlsx', 'xls'],
            [
                'text/csv', 'text/plain',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }

    public static function paymentProof(): self
    {
        return new self(
            ['jpg', 'jpeg', 'png', 'pdf'],
            ['image/jpeg', 'image/png', 'application/pdf'],
        );
    }

    public static function knowledgeBase(): self
    {
        // Tanpa html/htm: konten RAG dikutip kembali AI, HTML = XSS persisten.
        return new self(
            ['pdf', 'txt', 'md', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
            [
                'application/pdf',
                'text/plain', 'text/markdown', 'text/csv',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
        );
    }

    /**
     * Bersihkan nama file untuk keperluan display/link:
     * buang path & karakter kontrol, jaga nama asli agar tetap terbaca.
     */
    public static function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F<>":]/u', '', $name) ?? '';

        return trim($name) ?: 'file';
    }

    /**
     * Ekstensi yang aman disajikan inline: tidak bisa mengeksekusi script.
     * svg/html/txt/csv sengaja dikecualikan (bisa berisi JS atau formula).
     */
    public const INLINE_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp',
        'mp4', 'webm', 'ogg', 'mp3', 'wav',
    ];

    /** MIME finfo yang diterima per ekstensi inline. */
    public const EXTENSION_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'mp4' => ['video/mp4'], 'webm' => ['video/webm', 'audio/webm'],
        'ogg' => ['audio/ogg', 'video/ogg'], 'mp3' => ['audio/mpeg', 'audio/mp3'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
    ];

    /**
     * Sajikan file dari disk non-publik dengan aman:
     * MIME dari isi file (finfo), bukan klaim client/DB; hanya tipe aman
     * yang inline + nosniff, sisanya dipaksa download.
     */
    public static function fileResponse(string $disk, string $path, string $fileName): BinaryFileResponse|StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $safeName = self::sanitizeName($fileName);
        $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $absolute = Storage::disk($disk)->path($path);

        if (! in_array($extension, self::INLINE_EXTENSIONS, true)) {
            return Storage::disk($disk)->download($path, $safeName, ['X-Content-Type-Options' => 'nosniff']);
        }

        return response()->file($absolute, [
            'Content-Type' => self::serverMime($absolute, $extension),
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * MIME server-side: percaya finfo hanya bila cocok dengan ekstensi.
     * Mismatch (mis. PDF berisi HTML) → octet-stream + nosniff agar
     * browser mengunduh, bukan mengeksekusi.
     */
    public static function serverMime(string $absolutePath, string $extension): string
    {
        $allowed = self::EXTENSION_MIME_TYPES[$extension] ?? [];
        $actual = @mime_content_type($absolutePath);

        if ($allowed !== [] && in_array($actual, $allowed, true)) {
            return (string) $actual;
        }

        return 'application/octet-stream';
    }
}
