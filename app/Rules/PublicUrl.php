<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * URL publik http(s). Tolak localhost & jaringan privat/internal (SSRF),
 * termasuk metadata cloud 169.254.169.254.
 */
class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = parse_url((string) $value);

        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            $fail(__('URL tidak valid.'));

            return;
        }

        $host = strtolower($parts['host']);

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) {
            $fail(__('URL localhost/internal tidak diizinkan.'));

            return;
        }

        $ips = @gethostbynamel($host) ?: [];

        // Host yang sudah berupa IP literal ikut diperiksa.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        }

        if (!$ips) {
            $fail(__('Host tidak dapat dijangkau.'));

            return;
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || str_starts_with($ip, '169.254.')) {
                $fail(__('URL ke jaringan privat/internal tidak diizinkan.'));

                return;
            }
        }
    }
}
