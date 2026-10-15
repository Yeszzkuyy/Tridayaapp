<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationPreference;
use App\Models\WhatsappMessage;
use App\Notifications\NewLeadNotification;
use App\Notifications\WhatsappInboundNotification;
use App\Services\WhatsappBot;
use App\Services\WhatsappGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

class WhatsAppCenterController extends Controller
{
    public function __construct(
        private readonly WhatsappGateway $gateway,
        private readonly WhatsappBot $bot,
    ) {}

    public function index()
    {
        $accounts = $this->visibleAccounts()->get();
        $accountTabs = $accounts->map(fn ($a) => [
            'id' => $a->id,
            'account_code' => $a->account_code,
            'label' => $a->name ?: 'WA '.strtoupper(substr($a->account_code, 3)),
            'name' => $a->name,
            'phone_number' => $a->phone_number,
            'gateway_type' => $a->gateway_type,
            'gateway_status' => $a->gateway_status,
            'has_credentials' => filled($a->gateway_instance) && filled($a->gateway_token),
        ])->values();

        return view('whatsapp-center.index', compact('accounts', 'accountTabs'));
    }

    public function status()
    {
        $data = $this->visibleAccounts()->get()->map(function ($account) {
            return [
                'id' => $account->id,
                'account_code' => $account->account_code,
                'unread' => $this->unreadCount($account),
                'gateway_status' => $account->gateway_status,
                'has_credentials' => filled($account->gateway_instance) && filled($account->gateway_token),
            ];
        });

        return response()->json($data);
    }

    public function checkStatus(WhatsappAccount $account)
    {
        $this->authorizeAccount($account);

        // Cek status manual (satu kali), khusus untuk akun Meta agar tidak
        // memicu rate limit lewat polling otomatis tiap 5 detik.
        if (! $this->gateway->isMeta($account)) {
            return response()->json([
                'gateway_status' => $account->gateway_status,
            ]);
        }

        $check = $this->gateway->checkMetaConnection($account);
        $status = $check['status'];

        if ($status !== $account->gateway_status) {
            $account->update(['gateway_status' => $status]);
        }

        return response()->json([
            'gateway_status' => $status,
            'connected' => $check['connected'],
            'detail' => $check['detail'],
        ]);
    }

    public function conversations(WhatsappAccount $account)
    {
        $this->authorizeAccount($account);

        $preferences = WhatsappConversationPreference::where('user_id', auth()->id())
            ->where('whatsapp_account_id', $account->id)
            ->get()
            ->keyBy('sender_number');

        $conversations = WhatsappConversation::where('whatsapp_account_id', $account->id)
            ->with('handler:id,name')
            ->get()
            ->keyBy('sender_number');

        $blocked = $this->foreignOwnedSenderNumbers($account);

        // 1 query: pesan terakhir per pengirim (eager lead, tanpa N+1).
        $latestIds = WhatsappMessage::where('whatsapp_account_id', $account->id)
            ->selectRaw('max(id) as id')
            ->groupBy('sender_number')
            ->pluck('id');
        $lasts = WhatsappMessage::whereIn('id', $latestIds)
            ->with('lead:id')
            ->orderByDesc('id')
            ->get();

        $unreads = $this->senderUnreads($account);

        // Kandidat customer sekali per request (bukan per pengirim).
        $byNumber = [];
        foreach (Customer::whereNotNull('whatsapp')->orWhereNotNull('phone')->get(['id', 'whatsapp', 'phone']) as $candidate) {
            foreach ([$candidate->whatsapp, $candidate->phone] as $number) {
                $normalized = $this->normalizeNumber((string) $number);
                if ($normalized !== '' && ! isset($byNumber[$normalized])) {
                    $byNumber[$normalized] = $candidate->id;
                }
            }
        }
        $senders = $lasts->map(fn ($m) => $this->normalizeNumber((string) $m->sender_number))->filter()->unique()->values();
        $matchedIds = $senders->map(fn ($n) => $byNumber[$n] ?? null)->filter()->unique()->values()->all();
        $customersById = Customer::whereIn('id', $matchedIds)->get()->keyBy('id');

        $rows = $lasts
            ->map(function ($last) use ($preferences, $conversations, $account, $blocked, $unreads, $byNumber, $customersById) {
                $sender = (string) $last->sender_number;
                if (in_array($this->normalizeNumber($sender), $blocked, true)) {
                    return null;
                }
                $customer = isset($byNumber[$this->normalizeNumber($sender)])
                    ? ($customersById[$byNumber[$this->normalizeNumber($sender)]] ?? null)
                    : null;
                if ($customer && $customer->whatsapp_account_id && (int) $customer->whatsapp_account_id !== (int) $account->id) {
                    $customer = null;
                }
                $preference = $preferences->get($sender);
                $conversation = $conversations->get($sender);

                return [
                    'sender_number' => $sender,
                    'sender_name' => $last->sender_name ?? 'Kontak WA',
                    'last_message' => $last->message_body,
                    'last_direction' => $last->direction,
                    'last_at' => $last->created_at->toIso8601String(),
                    'unread' => (int) ($unreads[$sender] ?? 0),
                    'customer' => $customer,
                    'lead_id' => $last->lead?->id,
                    'is_pinned' => (bool) $preference?->is_pinned,
                    'is_muted' => (bool) $preference?->is_muted,
                    'is_archived' => (bool) $preference?->is_archived,
                    'mode' => $conversation?->mode ?? WhatsappConversation::MODE_BOT,
                    'handled_by' => $conversation?->handler?->name,
                    'bot_turns' => (int) ($conversation?->bot_turns ?? 0),
                ];
            })
            ->filter()
            ->values()
            ->sortByDesc(fn ($conversation) => ($conversation['is_pinned'] ? '1' : '0').$conversation['last_at'])
            ->values();

        return response()->json($rows);
    }

    /**
     * Hitungan unread eksak per pengirim dalam 1 query: inbound yang
     * belum dibaca dan datang setelah balasan outbound terakhir.
     */
    private function senderUnreads(WhatsappAccount $account): \Illuminate\Support\Collection
    {
        $perSender = WhatsappMessage::where('whatsapp_account_id', $account->id)
            ->selectRaw("sender_number, max(case when direction = 'outbound' then id else 0 end) as last_out")
            ->groupBy('sender_number');

        return WhatsappMessage::from('whatsapp_messages as m')
            ->joinSub($perSender, 't', 't.sender_number', '=', 'm.sender_number')
            ->where('m.whatsapp_account_id', $account->id)
            ->where('m.direction', 'inbound')
            ->whereNull('m.read_at')
            ->whereColumn('m.id', '>', 't.last_out')
            ->selectRaw('m.sender_number, count(*) as c')
            ->groupBy('m.sender_number')
            ->pluck('c', 'sender_number');
    }

    public function messages(Request $request, WhatsappAccount $account, string $sender)
    {
        $this->authorizeAccount($account);
        $this->ensureSenderAccessible($account, $sender);

        $limit = min(max($request->integer('limit', 50), 1), 100);
        $query = WhatsappMessage::where('whatsapp_account_id', $account->id)
            ->where('sender_number', $sender)
            ->when($request->filled('before'), fn ($query) => $query->where('id', '<', $request->integer('before')))
            ->when($request->filled('q'), fn ($query) => $query->where('message_body', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('id');

        $messages = $query->limit($limit + 1)->get();
        $hasMore = $messages->count() > $limit;
        $messages = $messages->take($limit)->sortBy('id')->values()
            ->map(fn ($m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'status' => $m->status,
                'message_body' => $m->message_body,
                'is_bot' => (bool) $m->is_bot,
                'created_at' => $m->created_at->format('d M H:i'),
                'created_iso' => $m->created_at->toIso8601String(),
            ]);

        return response()->json([
            'messages' => $messages,
            'has_more' => $hasMore,
            'next_before' => $hasMore ? $messages->first()['id'] : null,
            'customer' => $this->findCustomer($sender),
        ]);
    }

    public function contacts(Request $request, WhatsappAccount $account)
    {
        $this->authorizeAccount($account);

        $term = trim((string) $request->input('q', ''));
        $senders = $this->accountSenderNumbers($account);
        $customers = Customer::query()
            ->where(function ($query) use ($account, $senders) {
                $query->where('whatsapp_account_id', $account->id)
                    ->orWhereIn('id', Lead::where('whatsapp_account_id', $account->id)->select('customer_id'));
                if ($senders !== []) {
                    $query->orWhereIn('whatsapp', $senders)->orWhereIn('phone', $senders);
                }
            })
            ->where(function ($query) use ($account) {
                $query->whereNull('whatsapp_account_id')->orWhere('whatsapp_account_id', $account->id);
            })
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $query->whereLike(['name', 'company', 'whatsapp', 'phone', 'email'], $term);
                });
            })
            ->where(function ($query) {
                $query->whereNotNull('whatsapp')->orWhereNotNull('phone');
            })
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Customer $customer) => $this->contactPayload($customer));

        return response()->json($customers->values());
    }

    public function saveContact(Request $request, WhatsappAccount $account)
    {
        $this->authorizeAccount($account);
        $this->authorize('manage-marketing');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'whatsapp' => 'required|string|max:50',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $customer = $this->findCustomer($validated['whatsapp']);

        if ($customer && $customer->whatsapp_account_id && (int) $customer->whatsapp_account_id !== (int) $account->id) {
            abort(403, 'Kontak ini milik company lain.');
        }

        $contactName = trim($validated['name']);
        $company = trim((string) ($validated['company'] ?? ''));
        $number = $this->normalizeNumber($validated['whatsapp']);

        if (! $customer) {
            $dbName = $company !== '' ? $company : $contactName;
            $customer = Customer::create([
                'name' => $dbName,
                'company' => $company !== '' ? $company : $contactName,
                'contact_person' => $contactName,
                'whatsapp' => $number,
                'whatsapp_account_id' => $account->id,
                'address' => $validated['address'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        } else {
            $dbName = $company !== '' ? $company : ($customer->company ?: $contactName);
            $customer->update([
                'name' => $dbName,
                'company' => $company !== '' ? $company : $customer->company,
                'contact_person' => $contactName,
                'whatsapp' => $number,
                'whatsapp_account_id' => $customer->whatsapp_account_id ?? $account->id,
                'address' => filled($validated['address'] ?? null) ? $validated['address'] : $customer->address,
                'notes' => $validated['notes'] ?? $customer->notes,
            ]);
        }

        return response()->json($this->contactPayload($customer->fresh()));
    }

    public function markRead(Request $request, WhatsappAccount $account, string $sender)
    {
        $this->authorizeAccount($account);
        $this->ensureSenderAccessible($account, $sender);

        $read = $request->boolean('read', true);
        $updated = WhatsappMessage::where('whatsapp_account_id', $account->id)
            ->where('sender_number', $sender)
            ->where('direction', 'inbound')
            ->when($read, fn ($query) => $query->whereNull('read_at'))
            ->update(['read_at' => $read ? now() : null]);

        return response()->json(['updated' => $updated, 'read' => $read]);
    }

    public function preference(Request $request, WhatsappAccount $account, string $sender)
    {
        $this->authorizeAccount($account);
        $this->ensureSenderAccessible($account, $sender);

        $validated = $request->validate([
            'is_pinned' => 'sometimes|boolean',
            'is_muted' => 'sometimes|boolean',
            'is_archived' => 'sometimes|boolean',
        ]);

        $preference = WhatsappConversationPreference::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'whatsapp_account_id' => $account->id,
                'sender_number' => $sender,
            ],
            $validated,
        );

        return response()->json($preference->only(['is_pinned', 'is_muted', 'is_archived']));
    }

    public function store(Request $request, WhatsappAccount $account, string $sender)
    {
        $this->authorizeAccount($account);
        $this->authorize('manage-marketing');
        $this->ensureSenderAccessible($account, $sender);

        $validated = $request->validate([
            'message_body' => 'required|string|max:4000',
        ]);

        $sender = $this->normalizeNumber($sender);

        $message = WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => $sender,
            'sender_name' => WhatsappMessage::where('whatsapp_account_id', $account->id)
                ->where('sender_number', $sender)->first()?->sender_name,
            'message_body' => $validated['message_body'],
            'direction' => 'outbound',
            'status' => 'queued',
        ]);

        if (! $this->gateway->configured($account)) {
            $status = 'queued';
        } elseif ($gatewayMessageId = $this->gateway->sendText($account, $sender, $validated['message_body'])) {
            $message->update([
                'gateway_message_id' => $gatewayMessageId,
                'status' => 'sent',
            ]);
            $status = 'sent';
        } else {
            $message->update(['status' => 'failed']);
            $status = 'failed';
        }

        // Marketing ikut campur manual → ambil alih dari bot (sticky).
        $this->bot->takeover($account, $sender, auth()->user());

        return response()->json([
            'id' => $message->id,
            'direction' => 'outbound',
            'status' => $status,
            'message_body' => $message->message_body,
            'created_at' => $message->created_at->format('d M H:i'),
        ]);
    }

    public function takeover(WhatsappAccount $account, string $sender)
    {
        $this->authorizeAccount($account);
        $this->authorize('manage-marketing');
        $this->ensureSenderAccessible($account, $sender);

        $conversation = $this->bot->takeover($account, $this->normalizeNumber($sender), auth()->user());

        return response()->json([
            'mode' => $conversation->mode,
            'handled_by' => auth()->user()->name,
            'bot_turns' => $conversation->bot_turns,
        ]);
    }

    public function release(WhatsappAccount $account, string $sender)
    {
        $this->authorizeAccount($account);
        $this->authorize('manage-marketing');
        $this->ensureSenderAccessible($account, $sender);

        $conversation = $this->bot->release($account, $this->normalizeNumber($sender));

        return response()->json([
            'mode' => $conversation->mode,
            'handled_by' => null,
            'bot_turns' => $conversation->bot_turns,
        ]);
    }

    public function convert(Request $request, WhatsappAccount $account, string $sender)
    {
        $this->authorizeAccount($account);
        $this->authorize('manage-marketing');
        $this->ensureSenderAccessible($account, $sender);

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_address' => 'nullable|string|max:1000',
            'segment' => 'required|in:'.implode(',', LeadController::SEGMENTS),
            'kebutuhan' => 'nullable|string|max:2000',
        ]);

        $number = $this->normalizeNumber($sender);
        $customer = $this->findCustomer($number);

        if ($customer && $customer->whatsapp_account_id && (int) $customer->whatsapp_account_id !== (int) $account->id) {
            abort(403, 'Kontak ini milik company lain.');
        }

        if (! $customer) {
            $customer = Customer::create([
                'name' => $validated['customer_name'],
                'company' => $validated['customer_name'],
                'whatsapp' => $number,
                'whatsapp_account_id' => $account->id,
                'contact_person' => $validated['customer_name'],
                'address' => $validated['customer_address'] ?? null,
            ]);
        } else {
            $updates = [];
            if (! $customer->whatsapp_account_id) {
                $updates['whatsapp_account_id'] = $account->id;
            }
            if (blank($customer->address) && filled($validated['customer_address'] ?? null)) {
                $updates['address'] = $validated['customer_address'];
            }
            if ($updates) {
                $customer->update($updates);
            }
        }

        $ptGroup = strtoupper(substr($account->account_code, 3));
        if (! in_array($ptGroup, Lead::PT_GROUPS)) {
            $ptGroup = Lead::PT_GROUPS[0];
        }

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => $ptGroup,
            'whatsapp_account_id' => $account->id,
            'segment' => $validated['segment'],
            'source' => 'whatsapp',
            'status' => 'cool',
            'kebutuhan' => $validated['kebutuhan'] ?? null,
            'incoming_date' => now()->toDateString(),
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'action' => 'created',
            'changes' => [
                'source' => ['old' => null, 'new' => 'whatsapp'],
                'sender' => ['old' => null, 'new' => $sender],
            ],
        ]);

        WhatsappMessage::where('whatsapp_account_id', $account->id)
            ->where('sender_number', $sender)
            ->where('direction', 'inbound')
            ->whereNull('lead_id')
            ->latest('id')
            ->first()
            ?->update(['lead_id' => $lead->id]);

        if (empty($lead->assigned_to)) {
            Notification::send(
                User::permission('manage-sales-leads')->get(),
                new NewLeadNotification($lead)
            );
        }

        return response()->json(['lead_id' => $lead->id, 'redirect' => route('leads.show', $lead)]);
    }

    public function verifyWebhook(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expected = (string) config('whatsapp.meta.verify_token');

        if ($expected !== ''
            && $mode === 'subscribe'
            && $token
            && hash_equals($expected, $token)) {
            return response($challenge);
        }

        abort(403, 'Verifikasi webhook Meta gagal.');
    }

    public function webhook(Request $request)
    {
        $payload = $request->json()->all();

        if (data_get($payload, 'entry.0.changes.0.value')) {
            $this->verifyMetaSignature($request);

            return $this->handleMetaNotification($payload);
        }

        return $this->handleNotification($payload);
    }

    /**
     * Verifikasi HMAC X-Hub-Signature-256 dari Meta memakai app secret.
     * Dilewati bila META_WA_APP_SECRET kosong (dev/test) dengan peringatan log.
     * Green API tidak menandatangani webhook sehingga perlindungannya
     * berupa throttle route + akun is_active + error generik di bawah.
     */
    private function verifyMetaSignature(Request $request): void
    {
        $secret = (string) config('whatsapp.meta.app_secret');

        if ($secret === '') {
            try {
                Log::warning('Webhook Meta tanpa verifikasi signature (META_WA_APP_SECRET belum diset).');
            } catch (\Throwable) {
                // ponytail: best-effort — logging gagal (mis. izin file) tak boleh menggagalkan webhook.
            }

            return;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Forbidden.');
        }
    }

    /**
     * Proses notifikasi Green API (dipakai webhook & polling receiveNotification).
     */
    public function handleNotification(array $payload): JsonResponse
    {
        $type = data_get($payload, 'typeWebhook');

        if (! $type) {
            return $this->storeInboundFromLegacy($payload);
        }

        $instance = data_get($payload, 'instanceData.idInstance');
        $account = $instance
            ? WhatsappAccount::where('gateway_instance', $instance)
                ->where('is_active', true)
                ->first()
            : null;

        if (! $account) {
            return response()->json(['error' => 'rejected'], 422);
        }

        return match ($type) {
            'incomingMessageReceived' => $this->handleIncoming($account, $payload),
            'outgoingMessageStatus' => $this->handleOutgoingStatus($account, $payload),
            'instanceStatus' => $this->handleInstanceStatus($account, $payload),
            default => response()->json(['status' => 'ignored', 'type' => $type]),
        };
    }

    /**
     * Proses notifikasi webhook Meta WhatsApp Business Cloud API.
     * Akun dituju dicocokkan lewat value.metadata.phone_number_id
     * dengan kolom gateway_instance, sehingga mendukung banyak akun Meta.
     */
    public function handleMetaNotification(array $payload): JsonResponse
    {
        foreach (data_get($payload, 'entry.0.changes', []) as $change) {
            $value = data_get($change, 'value', []);
            $phoneNumberId = data_get($value, 'metadata.phone_number_id');

            if (! $phoneNumberId) {
                return response()->json(['error' => 'rejected'], 422);
            }

            $account = WhatsappAccount::where('gateway_type', WhatsappAccount::GATEWAY_META)
                ->where('gateway_instance', $phoneNumberId)
                ->where('is_active', true)
                ->first();

            if (! $account) {
                Log::warning('Webhook Meta untuk phone_number_id tak dikenal.', ['phone_number_id' => $phoneNumberId]);

                return response()->json(['error' => 'rejected'], 422);
            }

            foreach (data_get($value, 'statuses', []) as $status) {
                $id = data_get($status, 'id');
                WhatsappMessage::where('whatsapp_account_id', $account->id)
                    ->where(function ($q) use ($id) {
                        $q->where('wa_message_id', $id)
                            ->orWhere('gateway_message_id', $id);
                    })
                    ->update(['status' => data_get($status, 'status')]);
            }

            foreach (data_get($value, 'messages', []) as $msg) {
                $this->storeMetaInbound($account, $msg);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function storeMetaInbound(WhatsappAccount $account, array $msg): void
    {
        $messageId = data_get($msg, 'id');

        if ($messageId && WhatsappMessage::where('wa_message_id', $messageId)->exists()) {
            return;
        }

        $text = null;
        if (data_get($msg, 'type') === 'text') {
            $text = data_get($msg, 'text.body');
        } else {
            // Non-teks (gambar/audio/dokumen) tetap dicatat agar percakapan muncul di list.
            $text = '[Pesan '.data_get($msg, 'type', 'media').' diterima — belum didukung, minta pengirim kirim teks.]';
        }

        if ($text === null) {
            return;
        }

        $waId = data_get($msg, 'from');
        $senderName = data_get($msg, 'from') ?? 'Kontak WA';
        $timestamp = data_get($msg, 'timestamp');

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => $waId,
            'sender_name' => $senderName,
            'message_body' => $text,
            'direction' => 'inbound',
            'status' => 'inbound',
            'wa_message_id' => $messageId,
            'created_at' => $timestamp ? now()->setTimestamp((int) $timestamp) : now(),
        ]);

        $this->notifyInbound($account, (string) $waId, $text);
    }

    public function updateCredentials(Request $request, WhatsappAccount $account)
    {
        if (! auth()->user()->hasRole('super-admin') && ! auth()->user()->hasPermissionTo('manage-sales-leads')) {
            abort(403);
        }

        $validated = $request->validate([
            'gateway_instance' => 'nullable|string|max:255',
            'gateway_token' => 'nullable|string|max:255',
            'bot_enabled' => 'nullable|boolean',
        ]);

        // Token kosong = tidak diubah (input password sengaja tidak di-prefill di form).
        $account->update(array_merge(
            [
                'gateway_instance' => $validated['gateway_instance'] ?? null,
                'bot_enabled' => $request->boolean('bot_enabled'),
                'gateway_status' => null,
            ],
            filled($validated['gateway_token'] ?? null) ? ['gateway_token' => $validated['gateway_token']] : []
        ));

        if ($state = $this->gateway->getState($account)) {
            $account->update(['gateway_status' => $state]);
        }

        return redirect()->route('whatsapp-center.index')
            ->with('success', 'Kredensial gateway disimpan.');
    }

    /**
     * Pengaturan sapaan/instruksi bot per akun — boleh diubah tim marketing
     * (manage-marketing), tidak seperti kredensial gateway yang terbatas pengelola.
     */
    public function updateBotSettings(Request $request, WhatsappAccount $account)
    {
        $this->authorizeAccount($account);
        $this->authorize('manage-marketing');

        $validated = $request->validate([
            'bot_instructions' => 'nullable|string|max:2000',
        ]);

        $account->update(['bot_instructions' => $validated['bot_instructions'] ?? null]);

        return redirect()->route('whatsapp-center.index')
            ->with('success', 'Pengaturan bot disimpan.');
    }

    private function handleIncoming(WhatsappAccount $account, array $payload): JsonResponse
    {
        $messageData = data_get($payload, 'messageData', []);
        $type = data_get($messageData, 'typeMessage');

        $text = match ($type) {
            'textMessage' => data_get($messageData, 'textMessageData.textMessage'),
            'extendedTextMessage' => data_get($messageData, 'extendedTextMessageData.text'),
            default => null,
        };

        if ($text === null) {
            return response()->json(['status' => 'ignored', 'type' => $type]);
        }

        $chatId = data_get($payload, 'senderData.chatId', '');
        $idMessage = data_get($payload, 'idMessage');
        $senderNumber = preg_replace('/@.*$/', '', $chatId);

        if ($idMessage && WhatsappMessage::where('wa_message_id', $idMessage)->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => $senderNumber,
            'sender_name' => data_get($payload, 'senderData.senderName') ?? 'Kontak WA',
            'message_body' => $text,
            'direction' => 'inbound',
            'status' => 'inbound',
            'wa_message_id' => $idMessage,
            'created_at' => data_get($payload, 'timestamp') ? now()->setTimestamp(data_get($payload, 'timestamp')) : now(),
        ]);

        $this->notifyInbound($account, $senderNumber, $text);

        return response()->json(['status' => 'ok']);
    }

    private function handleOutgoingStatus(WhatsappAccount $account, array $payload): JsonResponse
    {
        WhatsappMessage::where('whatsapp_account_id', $account->id)
            ->where('gateway_message_id', data_get($payload, 'idMessage'))
            ->update(['status' => data_get($payload, 'status')]);

        return response()->json(['status' => 'ok']);
    }

    private function handleInstanceStatus(WhatsappAccount $account, array $payload): JsonResponse
    {
        $state = data_get($payload, 'stateInstance') ?? data_get($payload, 'body.stateInstance');

        if ($state) {
            $account->update(['gateway_status' => $state]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function storeInboundFromLegacy(array $payload): JsonResponse
    {
        $validated = Validator::make($payload, [
            'account_code' => 'required|string',
            'sender_number' => 'required|string',
            'sender_name' => 'nullable|string|max:255',
            'message_body' => 'required|string',
            'wa_message_id' => 'nullable|string',
        ])->validate();

        $account = WhatsappAccount::where('account_code', $validated['account_code'])
            ->where('is_active', true)
            ->first();

        if (! $account) {
            return response()->json(['error' => 'rejected'], 422);
        }

        if (! empty($validated['wa_message_id'])
            && WhatsappMessage::where('wa_message_id', $validated['wa_message_id'])->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => $validated['sender_number'],
            'sender_name' => $validated['sender_name'] ?? null,
            'message_body' => $validated['message_body'],
            'direction' => 'inbound',
            'wa_message_id' => $validated['wa_message_id'] ?? null,
        ]);

        $this->notifyInbound($account, (string) $validated['sender_number'], $validated['message_body']);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Notifikasi ke tim marketing saat percakapan baru masuk (sekali per 6 jam).
     */
    private function notifyInbound(WhatsappAccount $account, string $sender, string $preview): void
    {
        $recent = WhatsappMessage::where('whatsapp_account_id', $account->id)
            ->where('sender_number', $sender)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subHours(6))
            ->count();

        if ($recent > 1) {
            return;
        }

        Notification::send(
            User::permission('manage-marketing')->get(),
            new WhatsappInboundNotification(
                $account->id,
                $account->name ?: $account->account_code,
                $sender,
                mb_substr($preview, 0, 120),
            )
        );
    }

    public function simulate(Request $request, WhatsappAccount $account)
    {
        if (app()->isProduction()) {
            abort(404);
        }

        $this->authorizeAccount($account);

        $validated = $request->validate([
            'sender_number' => 'required|string',
            'sender_name' => 'nullable|string|max:255',
            'message_body' => 'required|string|max:4000',
        ]);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => $validated['sender_number'],
            'sender_name' => $validated['sender_name'] ?? 'Kontak Baru',
            'message_body' => $validated['message_body'],
            'direction' => 'inbound',
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function visibleAccounts()
    {
        $user = auth()->user();

        return WhatsappAccount::where('is_active', true)
            ->when(
                ! $user->hasRole('super-admin') && ! $user->hasPermissionTo('manage-sales-leads'),
                // Akun tanpa pemilik = pool bersama, terlihat semua marketing.
                fn ($q) => $q->where(fn ($qq) => $qq->where('assigned_to', $user->id)->orWhereNull('assigned_to'))
            )
            ->orderBy('account_code');
    }

    private function authorizeAccount(WhatsappAccount $account): void
    {
        if (! $this->visibleAccounts()->whereKey($account->id)->exists()) {
            abort(403);
        }
    }

    private function unreadCount(WhatsappAccount $account): int
    {
        $blocked = $this->foreignOwnedSenderNumbers($account);

        return (int) $this->senderUnreads($account)
            ->reject(fn ($count, $sender) => in_array($this->normalizeNumber((string) $sender), $blocked, true))
            ->sum();
    }

    private function findCustomer(string $number): ?Customer
    {
        $normalized = $this->normalizeNumber($number);

        if (! $normalized) {
            return null;
        }

        return Customer::whereNotNull('whatsapp')->orWhereNotNull('phone')->get()
            ->first(fn ($c) => $this->normalizeNumber($c->whatsapp ?? '') === $normalized
                || $this->normalizeNumber($c->phone ?? '') === $normalized);
    }

    /**
     * Isolasi antar-company: nomor milik akun lain atau yang riwayatnya
     * hanya ada di akun lain tidak bisa dibuka dari akun ini.
     */
    private function ensureSenderAccessible(WhatsappAccount $account, string $sender): void
    {
        $number = $this->normalizeNumber($sender);
        $customer = $number ? $this->findCustomer($number) : null;

        if ($customer && $customer->whatsapp_account_id && (int) $customer->whatsapp_account_id !== (int) $account->id) {
            abort(404);
        }

        $variants = array_values(array_unique(array_filter([$sender, $number])));
        $hasLocal = WhatsappConversation::where('whatsapp_account_id', $account->id)->whereIn('sender_number', $variants)->exists()
            || WhatsappMessage::where('whatsapp_account_id', $account->id)->whereIn('sender_number', $variants)->exists();

        if ($hasLocal) {
            return;
        }

        $historyElsewhere = $number !== '' && (
            WhatsappConversation::where('sender_number', $number)->where('whatsapp_account_id', '!=', $account->id)->exists()
            || WhatsappMessage::where('sender_number', $number)->where('whatsapp_account_id', '!=', $account->id)->exists()
        );

        if ($historyElsewhere) {
            abort(404);
        }
    }

    private function visibleCustomer(WhatsappAccount $account, string $sender): ?Customer
    {
        $customer = $this->findCustomer($sender);

        if ($customer && $customer->whatsapp_account_id && (int) $customer->whatsapp_account_id !== (int) $account->id) {
            return null;
        }

        return $customer;
    }

    private function accountSenderNumbers(WhatsappAccount $account): array
    {
        return WhatsappMessage::where('whatsapp_account_id', $account->id)->distinct()->pluck('sender_number')
            ->merge(WhatsappConversation::where('whatsapp_account_id', $account->id)->distinct()->pluck('sender_number'))
            ->filter()->unique()->values()->all();
    }

    private function foreignOwnedSenderNumbers(WhatsappAccount $account): array
    {
        return Customer::whereNotNull('whatsapp_account_id')
            ->where('whatsapp_account_id', '!=', $account->id)
            ->get(['whatsapp', 'phone'])
            ->flatMap(fn ($c) => [$this->normalizeNumber($c->whatsapp ?? ''), $this->normalizeNumber($c->phone ?? '')])
            ->filter()->unique()->values()->all();
    }

    private function normalizeNumber(string $number): string
    {
        return WhatsappGateway::normalizeNumber($number);
    }

    private function contactPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'contact_person' => $customer->contact_person,
            'company' => $customer->company,
            'whatsapp' => $customer->whatsapp ?? $customer->phone,
            'address' => $customer->address,
            'notes' => $customer->notes,
            'url' => route('customers.show', $customer),
        ];
    }
}
