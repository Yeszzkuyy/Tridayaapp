<?php

namespace App\Support;

use App\Notifications\FollowUpOverdueNotification;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\LeadTaskNotification;
use App\Notifications\NewLeadNotification;
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
    public static function present(DatabaseNotification $notification): array
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
                'url' => self::url($kind, $data),
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
                'title' => __('Lead baru'),
                'body' => (string) ($data['customer'] ?? __('New lead')),
                'chips' => [],
            ],
            'assigned' => [
                'app' => __('Lead'),
                'title' => __('Lead di-assign ke Anda'),
                'body' => (string) ($data['customer'] ?? __('New lead')),
                'chips' => [],
            ],
            'followup' => [
                'app' => __('Follow Up'),
                'title' => (string) ($data['title'] ?? __('Follow up jatuh tempo')),
                'body' => (string) ($data['preview'] ?? $data['customer'] ?? ''),
                'chips' => [self::chip(__('Jatuh tempo'), 'danger')],
            ],
            'task' => [
                'app' => __('Inside Sales'),
                'title' => (string) ($data['title'] ?? __('Task baru')),
                'body' => (string) ($data['preview'] ?? $data['customer'] ?? ''),
                'chips' => [],
            ],
            'schedule_assigned' => [
                'app' => __('Jadwal'),
                'title' => __('Jadwal teknisi baru'),
                'body' => (string) ($data['customer'] ?? ''),
                'chips' => self::textChip($data['preview'] ?? null, 'info'),
            ],
            'schedule_changed' => [
                'app' => __('Jadwal'),
                'title' => __('Jadwal teknisi berubah'),
                'body' => (string) ($data['customer'] ?? ''),
                'chips' => self::textChip($data['preview'] ?? null, 'info'),
            ],
            'schedule_reminder' => [
                'app' => __('Jadwal'),
                'title' => __('Pengingat jadwal teknisi'),
                'body' => (string) ($data['customer'] ?? ''),
                'chips' => self::textChip($data['preview'] ?? null, 'info'),
            ],
            'sales_schedule_assigned' => [
                'app' => __('Jadwal Sales'),
                'title' => __('Jadwal sales baru'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => array_filter([self::instantChip($data['start_at'] ?? null)]),
            ],
            'whatsapp' => [
                'app' => __('WhatsApp'),
                'title' => $class === WhatsappHandoffNotification::class
                    ? __('Butuh handoff WhatsApp')
                    : __('Pesan WhatsApp baru'),
                'body' => self::whatsappBody($data),
                'chips' => $class === WhatsappHandoffNotification::class
                    ? [self::chip(
                        ($data['reason'] ?? null) === 'limit'
                            ? __('Bot mencapai batas balasan')
                            : __('Bot menunggu ambil alih'),
                        'warning',
                    )]
                    : [],
            ],
            'proposal' => [
                'app' => __('Proposal'),
                'title' => __('Update proposal'),
                'body' => (string) ($data['proposal_number'] ?? ''),
                'chips' => [self::chip(
                    self::proposalLabel($data['event'] ?? null),
                    self::proposalTone($data['event'] ?? null),
                )],
            ],
            'technical_request_created' => [
                'app' => __('Technical Request'),
                'title' => __('Technical request baru'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [self::chip(
                    ($data['priority'] ?? null) === 'urgent' ? __('Mendesak') : __('Normal'),
                    ($data['priority'] ?? null) === 'urgent' ? 'danger' : 'default',
                )],
            ],
            'technical_request_reviewed' => [
                'app' => __('Technical Request'),
                'title' => __('Review technical request'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [self::chip(
                    self::decisionLabel($data['decision'] ?? null),
                    self::decisionTone($data['decision'] ?? null),
                )],
            ],
            'technical_work_completed' => [
                'app' => __('Technical Request'),
                'title' => __('Hasil teknis sudah tersedia'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [],
            ],
            'survey_created' => [
                'app' => __('Survey'),
                'title' => __('Survey dibuat'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [],
            ],
            'survey_report_uploaded' => [
                'app' => __('Survey'),
                'title' => __('Laporan survey diunggah'),
                'body' => (string) ($data['title'] ?? ''),
                'chips' => [],
            ],
            default => [
                'app' => __('Notifikasi'),
                'title' => (string) ($data['title'] ?? __('Notifikasi baru')),
                'body' => (string) ($data['preview'] ?? $data['customer'] ?? ''),
                'chips' => [],
            ],
        };
    }

    /** URL tujuan klik kartu — bentuk kanonik per tipe, termasuk baris lama. */
    private static function url(string $kind, array $data): ?string
    {
        return match ($kind) {
            'lead' => NewLeadNotification::indexUrl(),
            'assigned' => LeadAssignedNotification::myLeadsUrl($data['customer'] ?? null),
            'followup' => FollowUpOverdueNotification::indexUrl(),
            'whatsapp' => route('whatsapp-center.index'),
            default => is_string($data['url'] ?? null) && $data['url'] !== ''
                ? $data['url']
                : null,
        };
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

        return $customer !== '' ? $customer : __('Pesan baru');
    }

    private static function proposalLabel(?string $event): string
    {
        return match ($event) {
            'pending_technical' => __('Menunggu review teknis'),
            'returned_to_draft' => __('Dikembalikan ke draft'),
            'ready' => __('Siap dikirim'),
            'sent' => __('Sudah dikirim'),
            'expired' => __('Kedaluwarsa'),
            'accepted' => __('Diterima'),
            'rejected' => __('Ditolak'),
            default => $event !== null && $event !== '' ? Str::headline($event) : __('Update proposal'),
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
            'accepted' => __('Diterima'),
            'rejected' => __('Ditolak'),
            'assigned' => __('Diteruskan ke teknisi'),
            default => $decision !== null && $decision !== '' ? Str::headline($decision) : __('Direview'),
        };
    }

    private static function decisionTone(?string $decision): string
    {
        return match ($decision) {
            'accepted' => 'success',
            'rejected' => 'danger',
            default => 'info',
        };
    }
}
