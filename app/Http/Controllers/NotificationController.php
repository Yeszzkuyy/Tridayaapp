<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\FollowUpOverdueNotification;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\NewLeadNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function status(Request $request)
    {
        // itemsFor lebih dulu: ia menghapus baris basi (self-healing)
        // sehingga angka unread cocok dengan isi daftar.
        $items = self::itemsFor($request->user());

        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),
            'unassigned' => \Illuminate\Support\Facades\Cache::remember('leads:unassigned-count', 60, fn () => Lead::whereNull('assigned_to')->count()),
            'items' => $items,
        ]);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function read(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function destroy(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public static function itemsFor(User $user): array
    {
        $canManageLeads = $user->can('manage-sales-leads');
        $stale = [];
        $items = [];

        foreach ($user->notifications()->limit(10)->get() as $n) {
            $data = $n->data;

            // Assign lama yang lead-nya sudah pindah tangan / dihapus:
            // bukan lagi untuk user ini — hapus agar bell bersih.
            if ($n->type === LeadAssignedNotification::class) {
                $lead = Lead::find($data['lead_id'] ?? 0);
                if (! $lead || (int) $lead->assigned_to !== (int) $user->id) {
                    $stale[] = $n->id;

                    continue;
                }
                // Perbaiki URL baris lama (dulu leads.show) ke My Leads.
                $data['url'] = LeadAssignedNotification::myLeadsUrl($data['customer'] ?? null);
            }

            // Reminder follow-up lama menunjuk ke halaman detail;
            // arahkan ke daftar Follow Up (filter jatuh tempo).
            if (($data['type'] ?? null) === 'followup') {
                $data['url'] = FollowUpOverdueNotification::indexUrl();
            }

            // Notifikasi lead-baru untuk management yang nyasar ke user
            // tanpa permission manage-sales-leads (mis. sales): buang.
            if ($n->type === NewLeadNotification::class && ! $canManageLeads) {
                $stale[] = $n->id;

                continue;
            }

            // Notif lead-baru (baru maupun lama yang masih menyimpan URL
            // halaman edit): klik langsung ke Manage Sales.
            if ($n->type === NewLeadNotification::class) {
                $data['url'] = NewLeadNotification::indexUrl();
            }

            $isWhatsapp = ($data['type'] ?? null) === 'whatsapp';

            $items[] = [
                'id' => $n->id,
                'url' => $data['url']
                    ?? ($isWhatsapp
                        ? route('whatsapp-center.index')
                        : route('manage-sales.edit', $data['lead_id'] ?? 0)),
                'title' => $data['title'] ?? null,
                'customer' => $data['customer'] ?? __('New lead'),
                'preview' => $data['preview'] ?? null,
                'type' => $data['type'] ?? ($isWhatsapp ? 'whatsapp' : 'lead'),
                'read' => (bool) $n->read_at,
                'ago' => $n->created_at->diffForHumans(),
            ];
        }

        if ($stale !== []) {
            $user->notifications()->whereIn('id', $stale)->delete();
        }

        return $items;
    }
}
