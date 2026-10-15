<?php

namespace App\Services;

use App\Ai\Agents\LeadNeedsSummarizer;
use App\Ai\Agents\WhatsappSalesBot;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Notifications\WhatsappHandoffNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class WhatsappBot
{
    /**
     * Balasan cadangan bila AI gagal, agar customer tidak dibiarkan tanpa respons.
     */
    private const FALLBACK_REPLY = 'Terima kasih sudah menghubungi 3DY Group. Pesan Anda sudah kami terima, tim sales akan segera membalas.';

    public function __construct(private WhatsappGateway $gateway)
    {
    }

    /**
     * Ringkas kebutuhan customer dari transkrip chat (untuk prefill form Lead).
     */
    public function summarizeNeeds(WhatsappAccount $account, string $sender): ?string
    {
        $transcript = $this->transcript($account, $sender, 20);

        if ($transcript === '') {
            return null;
        }

        try {
            $text = trim((string) (new LeadNeedsSummarizer)->prompt($transcript));
        } catch (Throwable $e) {
            Log::error('WhatsappBot summarize gagal: '.$e->getMessage());

            return null;
        }

        return $text !== '' ? mb_substr($text, 0, 500) : null;
    }

    /**
     * Balas pesan customer yang belum dijawab untuk semua akun yang bot-nya aktif,
     * sekaligus kirim pengingat untuk percakapan yang sedang ditangani manusia.
     */
    public function replyPending(int $maxPerAccount = 5): int
    {
        $processed = 0;

        $accounts = WhatsappAccount::where('is_active', true)
            ->where('bot_enabled', true)
            ->get()
            ->filter(fn ($a) => $this->gateway->configured($a));

        foreach ($accounts as $account) {
            foreach ($this->pendingSenders($account)->take($maxPerAccount) as $sender) {
                // Kunci per percakapan: cron jalan tiap 30 detik, respons AI bisa
                // lebih lama — tanpa ini dua proses paralel membalas pesan yang sama.
                $lock = Cache::lock("wa-bot-reply:{$account->id}:{$sender}", 120);
                try {
                    $locked = $lock->acquire();
                } catch (Throwable $e) {
                    // ponytail: cache dir tak bisa ditulis (izin campur CLI/FPM,
                    // mis. setelah cache:clear) — lanjut tanpa lock, scheduler
                    // sudah withoutOverlapping. Lock gagal tak boleh mematikan bot.
                    Log::warning('WA bot lock gagal, lanjut tanpa lock: '.$e->getMessage());
                    $locked = true;
                    $lock = null;
                }
                if (! $locked) {
                    continue;
                }
                try {
                    if ($this->replyTo($account, $sender)) {
                        $processed++;
                    }
                } catch (Throwable $e) {
                    Log::error('WhatsappBot reply gagal: '.$e->getMessage(), [
                        'account' => $account->id,
                        'sender' => $sender,
                    ]);
                } finally {
                    $lock?->release();
                }
            }
        }

        $this->remindWaitingHumans();

        return $processed;
    }

    /**
     * Nomor pengirim yang menunggu balasan bot: pesan masuk terakhir lebih baru
     * dari balasan terakhir, dalam 24 jam, mode bot, dan belum mencapai batas.
     *
     * @return Collection<int, string>
     */
    public function pendingSenders(WhatsappAccount $account): Collection
    {
        $maxTurns = (int) config('whatsapp.bot.max_turns', 5);

        $conversations = WhatsappConversation::where('whatsapp_account_id', $account->id)
            ->get()
            ->keyBy('sender_number');

        $rows = WhatsappMessage::where('whatsapp_account_id', $account->id)
            ->orderBy('id')
            ->get()
            ->groupBy('sender_number');

        return $rows->filter(function ($messages, $sender) use ($conversations, $maxTurns) {
            $last = $messages->last();
            $lastInbound = $messages->where('direction', 'inbound')->last();

            if (! $lastInbound || $last->direction !== 'inbound') {
                return false;
            }

            if ($lastInbound->created_at->lt(now()->subDay())) {
                return false;
            }

            $conversation = $conversations->get((string) $sender);

            // Sticky takeover: percakapan mode human tidak pernah dibalas bot.
            if ($conversation?->isHuman()) {
                return false;
            }

            return ($conversation?->bot_turns ?? 0) < $maxTurns;
        })->keys()->map(fn ($sender) => (string) $sender)->values();
    }

    public function replyTo(WhatsappAccount $account, string $sender): bool
    {
        $maxTurns = (int) config('whatsapp.bot.max_turns', 5);
        $conversation = $this->conversationFor($account, $sender);

        if ($conversation->isHuman() || $conversation->bot_turns >= $maxTurns) {
            return false;
        }

        $reply = $this->generateReply($account, $sender);

        if (blank($reply)) {
            return false;
        }

        $message = WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => $sender,
            'sender_name' => WhatsappMessage::where('whatsapp_account_id', $account->id)
                ->where('sender_number', $sender)->first()?->sender_name,
            'message_body' => $reply,
            'direction' => 'outbound',
            'status' => 'queued',
            'is_bot' => true,
        ]);

        $gatewayId = $this->gateway->sendText($account, $sender, $reply);

        $message->update([
            'gateway_message_id' => $gatewayId,
            'status' => $gatewayId ? 'sent' : 'failed',
        ]);

        $conversation->increment('bot_turns');

        // Batas tercapai: simpan ringkasan kebutuhan + minta manusia mengambil alih.
        if ($conversation->fresh()->bot_turns >= $maxTurns) {
            $conversation->update(['needs_summary' => $this->summarizeNeeds($account, $sender)]);
            $this->notifyHandoff($account, $sender, 'limit', 'Bot mencapai batas balasan, perlu diambil alih.');
        }

        return $gatewayId !== null;
    }

    public function conversationFor(WhatsappAccount $account, string $sender): WhatsappConversation
    {
        return WhatsappConversation::firstOrCreate(
            ['whatsapp_account_id' => $account->id, 'sender_number' => $sender],
            ['mode' => WhatsappConversation::MODE_BOT],
        );
    }

    public function takeover(WhatsappAccount $account, string $sender, ?User $user = null): WhatsappConversation
    {
        $conversation = $this->conversationFor($account, $sender);
        $conversation->update([
            'mode' => WhatsappConversation::MODE_HUMAN,
            'handled_by' => $user?->id,
            'taken_over_at' => now(),
        ]);

        return $conversation;
    }

    public function release(WhatsappAccount $account, string $sender): WhatsappConversation
    {
        $conversation = $this->conversationFor($account, $sender);
        $conversation->update([
            'mode' => WhatsappConversation::MODE_BOT,
            'handled_by' => null,
            'bot_turns' => 0,
            'taken_over_at' => null,
            'reminded_at' => null,
        ]);

        return $conversation;
    }

    /**
     * Pengingat untuk percakapan mode human yang client-nya masih menunggu balasan.
     */
    public function remindWaitingHumans(): int
    {
        $minutes = (int) config('whatsapp.bot.reminder_minutes', 30);
        $sent = 0;

        $conversations = WhatsappConversation::where('mode', WhatsappConversation::MODE_HUMAN)
            ->where(function ($q) use ($minutes) {
                $q->whereNull('reminded_at')->orWhere('reminded_at', '<', now()->subMinutes($minutes));
            })
            ->get();

        foreach ($conversations as $conversation) {
            $messages = WhatsappMessage::where('whatsapp_account_id', $conversation->whatsapp_account_id)
                ->where('sender_number', $conversation->sender_number)
                ->orderBy('id')
                ->get();

            if ($messages->last()?->direction !== 'inbound') {
                continue;
            }

            $account = $conversation->account;

            if (! $account) {
                continue;
            }

            $lastInbound = $messages->where('direction', 'inbound')->last();
            $this->notifyHandoff($account, (string) $conversation->sender_number, 'waiting',
                'Customer menunggu balasan (ditangani manusia).');
            $conversation->update(['reminded_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    public function generateReply(WhatsappAccount $account, string $sender): ?string
    {
        $transcript = $this->transcript($account, $sender, 15);

        if ($transcript === '') {
            return null;
        }

        try {
            $text = trim((string) (new WhatsappSalesBot(filled($account->bot_instructions) ? trim((string) $account->bot_instructions) : null))->prompt($transcript));
        } catch (Throwable $e) {
            Log::error('WhatsappBot generateReply gagal: '.$e->getMessage());

            return self::FALLBACK_REPLY;
        }

        return $text !== '' ? mb_substr($text, 0, 1000) : self::FALLBACK_REPLY;
    }

    private function notifyHandoff(WhatsappAccount $account, string $sender, string $reason, string $preview): void
    {
        try {
            Notification::send(
                User::permission('manage-marketing')->get(),
                new WhatsappHandoffNotification(
                    $account->id,
                    $account->name ?: $account->account_code,
                    $sender,
                    $reason,
                    $preview,
                )
            );
        } catch (Throwable $e) {
            Log::error('WhatsappBot notifikasi handoff gagal: '.$e->getMessage());
        }
    }

    private function transcript(WhatsappAccount $account, string $sender, int $limit): string
    {
        return WhatsappMessage::where('whatsapp_account_id', $account->id)
            ->where('sender_number', $sender)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->map(function (WhatsappMessage $m) {
                $who = $m->direction === 'inbound' ? 'Customer' : 'Kami';
                $text = trim((string) $m->message_body);

                return $text !== '' ? "{$who}: {$text}" : null;
            })
            ->filter()
            ->implode("\n");
    }
}
