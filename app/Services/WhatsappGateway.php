<?php

namespace App\Services;

use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappGateway
{
    public function configured(WhatsappAccount $account): bool
    {
        return filled($account->gateway_instance) && filled($account->gateway_token);
    }

    /**
     * Kirim pesan teks via Green API. Mengembalikan idMessage dari gateway,
     * atau null bila akun belum dikonfigurasi / gagal terkirim.
     */
    public function isMeta(WhatsappAccount $account): bool
    {
        return $account->isMetaGateway();
    }

    public function sendText(WhatsappAccount $account, string $number, string $text): ?string
    {
        if (!$this->configured($account)) {
            return null;
        }

        if ($this->isMeta($account)) {
            return $this->sendTextMeta($account, $number, $text);
        }

        $response = Http::timeout(20)
            ->post(
                sprintf(
                    '%s/waInstance%s/sendMessage/%s',
                    rtrim(config('whatsapp.base_url'), '/'),
                    $account->gateway_instance,
                    $account->gateway_token
                ),
                [
                    'chatId' => $this->chatId($number),
                    'message' => $text,
                ]
            );

        if ($response->failed()) {
            report(new \RuntimeException('Green API sendMessage gagal: ' . $response->body()));
            return null;
        }

        return data_get($response->json(), 'idMessage');
    }

    /**
     * Ambil status koneksi instance dari Green API (getStateInstance).
     * Mengembalikan stateInstance (mis. 'authorized') atau null bila gagal.
     */
    public function getState(WhatsappAccount $account): ?string
    {
        if (!$this->configured($account)) {
            return null;
        }

        if ($this->isMeta($account)) {
            return $this->getStateMeta($account);
        }

        $response = Http::timeout(20)->get(sprintf(
            '%s/waInstance%s/getStateInstance/%s',
            rtrim(config('whatsapp.base_url'), '/'),
            $account->gateway_instance,
            $account->gateway_token
        ));

        if ($response->failed()) {
            return null;
        }

        return $response->json('stateInstance');
    }

    private function sendTextMeta(WhatsappAccount $account, string $number, string $text): ?string
    {
        $response = Http::timeout(20)
            ->withToken($account->gateway_token)
            ->post(
                sprintf(
                    '%s/%s/%s/messages',
                    rtrim(config('whatsapp.meta.graph_base_url'), '/'),
                    config('whatsapp.meta.api_version'),
                    $account->gateway_instance
                ),
                [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => self::normalizeNumber($number),
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $text,
                    ],
                ]
            );

        if ($response->failed()) {
            report(new \RuntimeException('Meta sendMessage gagal: ' . $response->body()));
            return null;
        }

        return data_get($response->json(), 'messages.0.id');
    }

    /**
     * Cek koneksi Meta sekali jalan untuk indikator UI: status + alasan
     * gagal (pesan error Meta, tanpa token). ID yang bukan Phone Number ID
     * terdeteksi di sini — Graph mengembalikan error #100.
     */
    public function checkMetaConnection(WhatsappAccount $account): array
    {
        if (! $this->configured($account)) {
            return ['status' => null, 'connected' => false, 'detail' => 'not_configured'];
        }

        $response = Http::timeout(20)
            ->withToken($account->gateway_token)
            ->get(sprintf(
                '%s/%s/%s',
                rtrim(config('whatsapp.meta.graph_base_url'), '/'),
                config('whatsapp.meta.api_version'),
                $account->gateway_instance
            ), [
                'fields' => 'display_phone_number',
            ]);

        // Pastikan ID benar-benar Phone Number ID (punya display_phone_number),
        // bukan App ID / ID lain yang kebetulan bisa di-GET.
        if ($response->successful() && $response->json('display_phone_number')) {
            return ['status' => 'authorized', 'connected' => true, 'detail' => null];
        }

        $detail = mb_substr((string) ($response->json('error.message') ?: 'http_'.$response->status()), 0, 200);
        Log::warning('Cek koneksi Meta gagal.', ['account_id' => $account->id, 'detail' => $detail]);

        return ['status' => null, 'connected' => false, 'detail' => $detail];
    }

    private function getStateMeta(WhatsappAccount $account): ?string
    {
        return $this->checkMetaConnection($account)['status'];
    }

    /**
     * Ambil satu notifikasi antrean dari Green API (polling).
     * Mengembalikan ['receiptId' => ..., 'payload' => [...]] atau null bila kosong.
     */
    public function receive(WhatsappAccount $account): ?array
    {
        if (!$this->configured($account)) {
            return null;
        }

        $response = Http::timeout(20)->get(sprintf(
            '%s/waInstance%s/receiveNotification/%s',
            rtrim(config('whatsapp.base_url'), '/'),
            $account->gateway_instance,
            $account->gateway_token
        ));

        if ($response->failed() || !$response->json()) {
            return null;
        }

        $receiptId = data_get($response->json(), 'receiptId');

        if (!$receiptId) {
            return null;
        }

        return [
            'receiptId' => $receiptId,
            'payload' => data_get($response->json(), 'body', []),
        ];
    }

    /**
     * Konfirmasi notifikasi sudah diproses (deleteNotification).
     */
    public function acknowledge(WhatsappAccount $account, int|string $receiptId): void
    {
        if (!$this->configured($account)) {
            return;
        }

        Http::timeout(20)->delete(sprintf(
            '%s/waInstance%s/deleteNotification/%s/%s',
            rtrim(config('whatsapp.base_url'), '/'),
            $account->gateway_instance,
            $account->gateway_token,
            $receiptId
        ));
    }

    private function chatId(string $number): string
    {
        return self::normalizeNumber($number) . '@c.us';
    }

    /**
     * Normalisasi nomor ke format internasional tanpa simbol (mis. 0812... -> 62812...).
     * Nomor pendek (mis. kode internal) dibiarkan apa adanya.
     */
    public static function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);

        if (str_starts_with($digits, '0') && strlen($digits) >= 9) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '8') && strlen($digits) >= 9) {
            return '62' . $digits;
        }

        return $digits;
    }
}