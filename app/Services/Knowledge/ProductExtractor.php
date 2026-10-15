<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Merapikan konten Firecrawl menjadi JSON produk via LLM.
 * LLM HANYA merapikan format dari konten — fakta harus dari konten,
 * tidak jelas/tidak ada -> null (hilir jadi NEEDS_REVIEW).
 */
class ProductExtractor
{
    /**
     * $context = ['title' => ..., 'url' => ...] dari metadata Firecrawl
     * (judul halaman adalah bagian konten resmi, boleh dipakai).
     */
    public function extract(string $markdown, array $context = []): array
    {
        // Model gratis tidak stabil (konten sama, hasil bisa beda):
        // coba maks 3 jendela, pakai yang paling lengkap.
        $best = null;
        $bestScore = -1;

        foreach ([12000, 4000, 1500] as $window) {
            $piece = mb_substr(trim($markdown), 0, $window);
            if ($piece === '') {
                continue;
            }

            $candidate = $this->callOnce($piece, $context);
            $score = $this->completeness($candidate);

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }

            if ($bestScore >= 4) {
                break;
            }
        }

        return $best ?? $this->blankResult();
    }

    protected function blankResult(): array
    {
        return [
            'brand' => null, 'name' => null, 'model' => null, 'sku' => null,
            'category' => null, 'description' => null, 'specifications' => null,
        ];
    }

    /**
     * Skor kelengkapan 0-7 untuk voting antar percobaan.
     */
    protected function completeness(array $result): int
    {
        $score = 0;
        foreach (['brand', 'name', 'model', 'sku', 'category', 'description', 'specifications'] as $field) {
            if (filled($result[$field] ?? null)) {
                $score++;
            }
        }

        return $score;
    }

    protected function callOnce(string $markdown, array $context = []): array
    {
        $key = (string) config('ai.providers.openrouter.key');
        if (!$key) {
            throw new RuntimeException('OPENROUTER_API_KEY belum dikonfigurasi.');
        }

        $text = mb_substr(trim($markdown), 0, 12000);
        if ($text === '') {
            throw new RuntimeException('Konten halaman kosong.');
        }

        $hints = [];
        if (filled($context['title'] ?? null)) {
            $hints[] = 'Page title: '.mb_substr((string) $context['title'], 0, 300);
        }
        if (filled($context['url'] ?? null)) {
            $hints[] = 'Page URL: '.mb_substr((string) $context['url'], 0, 300);
        }
        $hintText = $hints ? implode("\n", $hints)."\n\n" : '';

        $res = Http::withToken($key)
            ->timeout(120)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => config('ai.providers.openrouter.models.text.cheapest'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You extract official product facts from page content into JSON with keys: '
                            .'brand, name, model, sku, category, description, specifications. '
                            .'RULES: use ONLY facts visible in the content, page title, or page URL. Never invent or guess. '
                            .'Missing or unclear field -> null. specifications is a concise plain-text spec list or null. '
                            .'Respond with JSON only.',
                    ],
                    ['role' => 'user', 'content' => $hintText.$text],
                ],
            ]);

        if (!$res->successful()) {
            throw new RuntimeException('Ekstraksi gagal: HTTP '.$res->status());
        }

        // OpenRouter kadang balas 200 berisi error upstream (limit gratis habis).
        // Itu BUKAN hasil kosong: lempar agar job FAILED + antre ulang.
        if ($res->json('error')) {
            throw new RuntimeException('Ekstraksi gagal: '.mb_substr((string) ($res->json('error.message') ?? 'upstream error'), 0, 200));
        }

        $json = $res->json('choices.0.message.content');
        if (!is_string($json) || trim($json) === '') {
            throw new RuntimeException('Ekstraksi gagal: respons model kosong.');
        }
        $data = is_string($json) ? json_decode($json, true) : (array) $json;

        if (!is_array($data)) {
            throw new RuntimeException('Ekstraksi gagal: respons bukan JSON.');
        }

        return [
            'brand' => $this->clean($data['brand'] ?? null),
            'name' => $this->clean($data['name'] ?? null),
            'model' => $this->clean($data['model'] ?? null),
            'sku' => $this->clean($data['sku'] ?? null),
            'category' => $this->clean($data['category'] ?? null),
            'description' => $this->clean($data['description'] ?? null, 2000),
            'specifications' => $this->clean($data['specifications'] ?? null, 8000),
        ];
    }

    protected function clean(mixed $value, int $max = 255): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($value === '' || strtolower($value) === 'null' || strtolower($value) === 'n/a') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
