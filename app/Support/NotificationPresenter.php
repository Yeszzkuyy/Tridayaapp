<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\FollowUpOverdueNotification;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\LeadTaskNotification;
use App\Notifications\MeetingReportReminderNotification;
use App\Notifications\NewLeadNotification;
use App\Notifications\PriceRequestCompletedNotification;
use App\Notifications\PriceRequestCommentNotification;
use App\Notifications\PriceRequestCreatedNotification;
use App\Notifications\PriceRequestReviewedNotification;
use App\Notifications\ProjectConvertedNotification;
use App\Notifications\ProposalStatusNotification;
use App\Notifications\SalesScheduleAssignedNotification;
use App\Notifications\SurveyCreatedNotification;
use App\Notifications\SurveyReportUploadedNotification;
use App\Notifications\TechnicalRequestCreatedNotification;
use App\Notifications\TechnicalRequestReviewedNotification;
use App\Notifications\TechnicalWorkCompletedNotification;
use App\Notifications\TechnicianScheduleAssignedNotification;
use App\Notifications\TechnicianScheduleChangedNotification;
use App\Notifications\TechnicianScheduleReminderNotification;
use App\Notifications\WhatsappHandoffNotification;
use App\Notifications\WhatsappInboundNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Ubah baris `notifications` apa pun (format lama maupun baru) menjadi item
 * feed dengan bentuk seragam yang dikonsumsi Notification Center:
 *
 *   ['id', 'type', 'app', 'title', 'body', 'chips', 'url', 'read', 'created_at']
 *
 * Satu-satunya tempat pemetaan tampilan — dipakai muatan awal, paginasi, dan
 * event realtime — sehingga frontend selalu menerima bentuk yang sama dan
 * baris lama tetap tampil rapi tanpa migrasi data.
 */
final class NotificationPresenter
{
    /**
     * @return array{id:string,type:string,app:string,title:string,body:string,chips:array<int,array{label:string,tone:string}>,url:?string,read:bool,created_at:?string}
     */
    public static function present(DatabaseNotification $notification, ?User $user = null): array
    {
        $data = $notification->data;
        $kind = self::kind((string) $notification->type, $data);

        return array_merge(
            [
                'id' => (string) $notification->id,
                'type' => $kind,
                'read' => $notification->read_at !== null,
                'created_at' => optional($notification->created_at)->toIso8601String(),
            ],
            self::content((string) $notification->type, $kind, $data),
            [
                'url' => self::url($kind, $data, $user ?? $notification->notifiable),
            ],
        );
    }

    /**
     * Jenis tampilan kartu. Utamakan kelas notifikasi; baris tanpa kelas
     * yang dikenal jatuh ke `type` yang tersimpan di payload.
     */
    public static function kind(string $class, array $data): string
    {
        return match ($class) {
            NewLeadNotification::class => 'lead',
            LeadAssignedNotification::class => 'assigned',
            FollowUpOverdueNotification::class => 'followup',
            LeadTaskNotification::class => 'task',
            TechnicianScheduleAssignedNotification::class => 'schedule_assigned',
            TechnicianScheduleChangedNotification::class => 'schedule_changed',
            TechnicianScheduleReminderNotification::class => 'schedule_reminder',
            SalesScheduleAssignedNotification::class => 'sales_schedule_assigned',
            WhatsappInboundNotification::class,
            WhatsappHandoffNotification::class => 'whatsapp',
            ProposalStatusNotification::class => 'proposal',
            TechnicalRequestCreatedNotification::class => 'technical_request_created',
            TechnicalRequestReviewedNotification::class => 'technical_request_reviewed',
            TechnicalWorkCompletedNotification::class => 'technical_work_completed',
            PriceRequestCreatedNotification::class => 'price_request_created',
            PriceRequestReviewedNotification::class => 'price_request_reviewed',
            PriceRequestCompletedNotification::class => 'price_request_completed',
            PriceRequestCommentNotification::class => 'price_request_comment',
            ProjectConvertedNotification::class => 'project_converted',
            MeetingReportReminderNotification::class => 'meeting_report_reminder',
            SurveyCreatedNotification::class => 'survey_created',
            SurveyReportUploadedNotification::class => 'survey_report_uploaded',
            default => self::legacyKind($data),
        };
    }

    /** Baris tanpa kelas yang dikenal: pakai `type` yang tersimpan bila masuk akal. */
    private static function legacyKind(array $data): string
    {
        $type = $data['type'] ?? null;

        return is_string($type) && $type !== '' ? $type : 'generic';
    }

    /** @return array{app:string,title:string,body:string,chips:array<int,array{label:string,tone:string}>} */
    private static function content(string $class, string $kind, array $data): array
    {
        return match ($kind) {
            'lead' => [
                'app' => __('Lead'),
                'title' => __('New lead'),
                'body' => (string) ($data['customer'] ?? __('New lead')),
                'chips' => [],
            ],
            'assigned' => [
                'app' => __('Lead'),
                'title' => __('Lead assigned to you'),
                'body' => (string) ($data['customer'] ?? __('New lead')),
                'chips' => [],
            ],
            'followup' => [
                'app' => __('Follow Up'),
                'title' => (string) ($data['title'] ?? __('Follow up overdue')),
                'body' => (string) ($data['preview'] ?? $data['customer'] ?? ''),
                'chips' => [self::chip(__('Due'), 'danger')],
            ],
            'task' => [
                'app' => __('Inside Sales'),
                'title' => (string) ($data['title'] ?? __('New task')),
                'body' => (string) ($data['preview'] ?? $data['customer'] ?? ''),
                'chips' => [],
            ],
            'schedule_assigned' => [
                'app' => __('Jadwal'),
                'title' => __('New technician schedule'),
                'body' => (string) ($data['customer'] ?? ''),
                'chips' => self::textChip($data['preview'] ?? null, 'info'),
            ],
            'schedule_changed' => [
                'app' => __('Jadwal'),
                'title' => __('Technician schedule changed'),
                'body' => (string) ($data['customer'] ?? ''),
                'chips' => self::textChip($data['preview'] ?? null, 'info'),
            ],
            'schedule_reminder' => [
                'app' => __('Jadwal'),
                'title' => __('Technician schedule reminder'),
                'body' => (string) ($data['customer'] ?? ''),
                'chips' => self::textChip($data['preview'] ?? null, 'info'),
            ],
            'sales_schedule_assigned' => [
                'app' => __('Sales Schedule'),
                'title' => __('New sales schedule'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => array_filter([self::instantChip($data['start_at'] ?? null)]),
            ],
            'whatsapp' => [
                'app' => __('WhatsApp'),
                'title' => $class === WhatsappHandoffNotification::class
                    ? __('WhatsApp handoff needed')
                    : __('New WhatsApp message'),
                'body' => self::whatsappBody($data),
                'chips' => $class === WhatsappHandoffNotification::class
                    ? [self::chip(
                        ($data['reason'] ?? null) === 'limit'
                            ? __('Bot reached its reply limit')
                            : __('Bot is waiting to be taken over'),
                        'warning',
                    )]
                    : [],
            ],
            'proposal' => [
                'app' => __('Proposal'),
                'title' => __('Proposal update'),
                'body' => (string) ($data['proposal_number'] ?? ''),
                'chips' => [self::chip(
                    self::proposalLabel($data['event'] ?? null),
                    self::proposalTone($data['event'] ?? null),
                )],
            ],
            'technical_request_created' => [
                'app' => __('Technical Request'),
                'title' => __('New technical request'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => ($data['type_label'] ?? null) ? [self::chip((string) $data['type_label'], 'info')] : [],
            ],
            'technical_request_reviewed' => [
                'app' => __('Technical Request'),
                'title' => __('Technical request review'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [self::chip(
                    self::decisionLabel($data['decision'] ?? null),
                    self::decisionTone($data['decision'] ?? null),
                )],
            ],
            'technical_work_completed' => [
                'app' => __('Technical Request'),
                'title' => __('Technical result is available'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [],
            ],
            'price_request_created' => [
                'app' => __('Price Request'),
                'title' => __('New price request'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [],
            ],
            'price_request_reviewed' => [
                'app' => __('Price Request'),
                'title' => __('Price request update'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [self::chip(
                    self::decisionLabel($data['decision'] ?? null),
                    self::decisionTone($data['decision'] ?? null),
                )],
            ],
            'price_request_completed' => [
                'app' => __('Price Request'),
                'title' => __('Price reply is available'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [],
            ],
            'price_request_comment' => [
                'app' => __('Price Request'),
                'title' => __('New message in Price Request'),
                'body' => trim((string) ($data['sender_name'] ?? '').': '.(string) ($data['preview'] ?? '')),
                'chips' => [],
            ],
            'project_converted' => [
                'app' => __('Project'),
                'title' => __('New project from Sales'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [],
            ],
            'meeting_report_reminder' => [
                'app' => __('Meeting'),
                'title' => __('Meeting report pending'),
                'body' => (string) ($data['customer'] ?? ''),
                'chips' => [],
            ],
            'survey_created' => [
                'app' => __('Survey'),
                'title' => __('Survey created'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [],
            ],
            'survey_report_uploaded' => [
                'app' => __('Survey'),
                'title' => __('Survey report uploaded'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [],
            ],
            default => [
                'app' => __('Notifications'),
                'title' => (string) ($data['title'] ?? __('New notification')),
                'body' => (string) ($data['preview'] ?? $data['customer'] ?? ''),
                'chips' => [],
            ],
        };
    }

    /**
     * URL tujuan klik kartu — bentuk kanonik per tipe, termasuk baris lama.
     *
     * Selalu dipaksa menjadi path relatif: notifikasi lama mungkin menyimpan
     * domain lama/berbeda (mis. http://tridayaapp.com) sementara user sedang
     * membuka domain kanonis. Pindah host membuat cookie session tidak
     * terkirim dan user dilempar ke halaman login.
     */
    private static function url(string $kind, array $data, mixed $notifiable = null): ?string
    {
        return match ($kind) {
            'lead' => self::relativeUrl(NewLeadNotification::indexUrl()),
            'assigned' => self::relativeUrl(LeadAssignedNotification::myLeadsUrl($data['customer'] ?? null)),
            'followup' => self::relativeUrl(FollowUpOverdueNotification::indexUrl()),
            'whatsapp' => self::relativeUrl(route('whatsapp-center.index')),
            'task' => self::taskUrl($data, $notifiable),
            default => self::relativeUrl(is_string($data['url'] ?? null) ? $data['url'] : null),
        };
    }

    /**
     * Deep link diskusi inside sales: ke menu Request → Inside Sales dengan
     * panel diskusi task terbuka. Penerima tanpa akses sales (inside-sales
     * murni) tetap diarahkan ke halaman task miliknya.
     */
    private static function taskUrl(array $data, mixed $notifiable = null): ?string
    {
        $taskId = (int) ($data['lead_task_id'] ?? 0);

        // Baris notifikasi lama tidak punya lead_task_id — ambil dari URL
        // /lead-tasks/{id} yang tersimpan.
        if ($taskId <= 0 && is_string($data['url'] ?? null)
            && preg_match('#/lead-tasks/(\d+)#', $data['url'], $matches)) {
            $taskId = (int) $matches[1];
        }

        if ($taskId <= 0) {
            return self::relativeUrl(is_string($data['url'] ?? null) ? $data['url'] : null);
        }

        $useRequestHub = $notifiable instanceof User
            && ($notifiable->can('view-sales')
                || $notifiable->can('manage-sales')
                || $notifiable->can('manage-sales-leads'));

        return $useRequestHub
            ? route('sales.requests.index', ['tab' => 'inside-sales', 'task' => $taskId], false)
            : route('lead-tasks.show', $taskId, false);
    }

    /**
     * Ubah URL absolut internal menjadi path relatif (path + query + fragment).
     * URL eksternal / host tak dikenal dibiarkan apa adanya.
     */
    private static function relativeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        // Bukan URL absolut (mis. "sales/my-leads"): biarkan apa adanya.
        if (! isset($parts['scheme'])) {
            return $url;
        }

        if (! in_array($parts['scheme'], ['http', 'https'], true)) {
            return $url;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $trustedHosts = array_filter([
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            strtolower((string) request()?->getHost()),
            'tridayaapp.com',
            'www.tridayaapp.com',
        ]);

        if ($host === '' || ! in_array($host, $trustedHosts, true)) {
            return $url;
        }

        $path = ($parts['path'] ?? '') !== '' ? $parts['path'] : '/';

        if (isset($parts['query'])) {
            $path .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $path .= '#'.$parts['fragment'];
        }

        return $path;
    }

    /** @return array{label:string,tone:string} */
    private static function chip(string $label, string $tone = 'default'): array
    {
        return ['label' => $label, 'tone' => $tone];
    }

    /** @return array<int,array{label:string,tone:string}> */
    private static function textChip(mixed $text, string $tone): array
    {
        return is_string($text) && $text !== '' ? [self::chip($text, $tone)] : [];
    }

    /** @return array{label:string,tone:string}|null */
    private static function instantChip(mixed $iso): ?array
    {
        if (! is_string($iso) || $iso === '') {
            return null;
        }

        try {
            $at = Carbon::parse($iso);
        } catch (\Throwable) {
            return null;
        }

        return self::chip($at->translatedFormat('d M Y, H:i'), 'info');
    }

    private static function whatsappBody(array $data): string
    {
        $customer = trim((string) ($data['customer'] ?? ''));
        $preview = trim((string) ($data['preview'] ?? ''));

        if ($customer !== '' && $preview !== '') {
            return $customer.': '.$preview;
        }

        if ($preview !== '') {
            return $preview;
        }

        return $customer !== '' ? $customer : __('New message');
    }

    private static function proposalLabel(?string $event): string
    {
        return match ($event) {
            'pending_technical' => __('Waiting for technical review'),
            'returned_to_draft' => __('Returned to draft'),
            'ready' => __('Ready to send'),
            'sent' => __('Sudah dikirim'),
            'expired' => __('Kedaluwarsa'),
            'accepted' => __('Accepted'),
            'rejected' => __('Rejected'),
            default => $event !== null && $event !== '' ? Str::headline($event) : __('Proposal update'),
        };
    }

    private static function proposalTone(?string $event): string
    {
        return match ($event) {
            'pending_technical', 'returned_to_draft' => 'warning',
            'ready', 'accepted' => 'success',
            'sent' => 'info',
            'expired', 'rejected' => 'danger',
            default => 'default',
        };
    }

    private static function decisionLabel(?string $decision): string
    {
        return match ($decision) {
            'accepted' => __('Accepted'),
            'rejected' => __('Rejected'),
            'assigned' => __('Forwarded to technician'),
            'revision' => __('Revision Requested'),
            default => $decision !== null && $decision !== '' ? Str::headline($decision) : __('Reviewed'),
        };
    }

    private static function decisionTone(?string $decision): string
    {
        return match ($decision) {
            'accepted' => 'success',
            'rejected' => 'danger',
            'revision' => 'warning',
            default => 'info',
        };
    }
}
