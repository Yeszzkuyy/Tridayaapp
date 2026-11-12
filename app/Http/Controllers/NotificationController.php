<?php

namespace App\Http\Controllers;

use App\Events\NotificationDeleted;
use App\Events\NotificationRead;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\NewLeadNotification;
use App\Notifications\PurchasingHandoffNotification;
use App\Support\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    /** Jumlah item per halaman feed. */
    private const PAGE_SIZE = 10;

    public function status(Request $request)
    {
        // pageFor lebih dulu: ia menghapus baris basi (self-healing)
        // sehingga angka unread cocok dengan isi daftar.
        $page = self::pageFor($request->user());

        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),
            'unassigned' => Cache::remember('leads:unassigned-count', 60, fn () => Lead::whereNull('assigned_to')->count()),
            'items' => $page['items'],
            'next_cursor' => $page['next_cursor'],
            'has_more' => $page['has_more'],
        ]);
    }

    /** Halaman berikutnya dari feed (cursor pagination, 10 item per halaman). */
    public function feed(Request $request)
    {
        $validated = $request->validate(['cursor' => 'nullable|string|max:512']);

        return response()->json(self::pageFor($request->user(), $validated['cursor'] ?? null));
    }

    public function readAll(Request $request)
    {
        $user = $request->user();
        $ids = $user->unreadNotifications()->pluck('id')->all();
        $user->unreadNotifications()->update(['read_at' => now()]);

        if ($ids !== []) {
            NotificationRead::dispatch($user->id, $ids, 0, 'all');
        }

        return $request->wantsJson()
            ? response()->json(['unread' => 0])
            : back();
    }

    public function read(Request $request, string $notification)
    {
        $user = $request->user();
        $user->notifications()->findOrFail($notification)->markAsRead();
        $unread = $user->unreadNotifications()->count();

        NotificationRead::dispatch($user->id, [$notification], $unread, 'single');

        return response()->json(['unread' => $unread]);
    }

    public function destroy(Request $request, string $notification)
    {
        $user = $request->user();
        $user->notifications()->findOrFail($notification)->delete();
        $unread = $user->unreadNotifications()->count();

        NotificationDeleted::dispatch($user->id, [$notification], $unread, 'single');

        return response()->json(['unread' => $unread]);
    }

    /** Hapus semua notifikasi user (read + unread). */
    public function destroyAll(Request $request)
    {
        $user = $request->user();
        $deleted = $user->notifications()->delete();

        NotificationDeleted::dispatch($user->id, [], 0, 'all');

        return response()->json(['unread' => 0, 'deleted' => $deleted]);
    }

    /**
     * Item halaman pertama — bentuk lama dipertahankan (daftar item saja)
     * untuk pemakaian internal & test.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function itemsFor(User $user): array
    {
        return self::pageFor($user)['items'];
    }

    /**
     * Satu halaman feed: urut `created_at desc, id desc`, cursor komposit
     * agar item detik yang sama tidak terlewat, plus self-healing baris basi.
     *
     * @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    public static function pageFor(User $user, ?string $cursor = null): array
    {
        $query = $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($cursor !== null && ($position = self::decodeCursor($cursor)) !== null) {
            [$createdAt, $id] = $position;
            $query->where(function ($q) use ($createdAt, $id) {
                $q->where('created_at', '<', $createdAt)
                    ->orWhere(function ($q) use ($createdAt, $id) {
                        $q->where('created_at', '=', $createdAt)->where('id', '<', $id);
                    });
            });
        }

        $rows = $query->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $rows->count() > self::PAGE_SIZE;
        $rows = $rows->take(self::PAGE_SIZE);

        $canManageLeads = $user->can('manage-sales-leads');
        $assignedLeadOwners = self::assignedLeadOwners($rows);

        $items = [];
        $stale = [];

        foreach ($rows as $row) {
            // Assign lama yang lead-nya sudah pindah tangan / dihapus:
            // bukan lagi untuk user ini — hapus agar bell bersih.
            if ($row->type === LeadAssignedNotification::class) {
                $leadId = (int) ($row->data['lead_id'] ?? 0);
                if (($assignedLeadOwners[$leadId] ?? null) !== (int) $user->id) {
                    $stale[] = $row->id;

                    continue;
                }
            }

            // Notifikasi lead-baru untuk management yang nyasar ke user
            // tanpa permission manage-sales-leads (mis. sales): buang.
            if ($row->type === NewLeadNotification::class && ! $canManageLeads) {
                $stale[] = $row->id;

                continue;
            }

            // Customer PO bukan urusan Estimator. Notifikasi lama yang
            // terlanjur masuk (sebelum role berubah) dibersihkan.
            if ($row->type === PurchasingHandoffNotification::class && ! $user->can('view-admin-hub')) {
                $stale[] = $row->id;

                continue;
            }

            $items[] = NotificationPresenter::present($row, $user);
        }

        if ($stale !== []) {
            $user->notifications()->whereIn('id', $stale)->delete();
        }

        $last = $rows->last();

        return [
            'items' => $items,
            'next_cursor' => $hasMore && $last !== null ? self::encodeCursor($last) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * assigned_to terkini untuk semua lead yang muncul di halaman ini —
     * satu query batch, bukan satu `Lead::find` per notifikasi (dulu N+1).
     *
     * @return array<int, int>
     */
    private static function assignedLeadOwners(Collection $rows): array
    {
        $leadIds = $rows
            ->filter(fn ($row) => $row->type === LeadAssignedNotification::class)
            ->map(fn ($row) => (int) ($row->data['lead_id'] ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($leadIds === []) {
            return [];
        }

        return Lead::whereIn('id', $leadIds)
            ->pluck('assigned_to', 'id')
            ->map(fn ($owner) => (int) $owner)
            ->all();
    }

    private static function encodeCursor(DatabaseNotification $row): string
    {
        $raw = $row->created_at->format('Y-m-d H:i:s').'|'.$row->id;

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** @return array{0:string,1:string}|null */
    private static function decodeCursor(string $cursor): ?array
    {
        $padded = $cursor.str_repeat('=', (4 - strlen($cursor) % 4) % 4);
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        if ($decoded === false || ! str_contains($decoded, '|')) {
            return null;
        }

        [$createdAt, $id] = explode('|', $decoded, 2);

        return $createdAt !== '' && $id !== '' ? [$createdAt, $id] : null;
    }
}
