<?php

namespace App\Support;

use App\Models\FollowUp;
use App\Models\Lead;

/**
 * Data siap pakai untuk baris workspace Sales (My Leads & Follow Up).
 * Satu tempat supaya tabel desktop dan kartu mobile memakai nilai yang sama.
 */
class LeadWorkspacePresenter
{
    public static function row(Lead $lead): array
    {
        $next = $lead->activeFollowUp;
        $latestNote = $lead->latestNoteFollowUp;

        return [
            'stage' => $lead->status,
            'stageLabel' => ucfirst($lead->status),
            'note' => (string) ($latestNote?->description ?? ''),
            'hasAcceptedProposal' => (bool) ($lead->has_accepted_proposal ?? false),
            'noteUrl' => route('sales.my-leads.note', $lead),
            'nextUrl' => route('sales.my-leads.next-follow-up', $lead),
            'statusUrl' => route('leads.update-status', $lead),
            'nextDate' => $next?->follow_up_date?->format('Y-m-d') ?? '',
            'nextTime' => $next?->activity_time ? substr((string) $next->activity_time, 0, 5) : '',
            'nextLabel' => $next?->follow_up_date?->format('d M Y') ?? '',
            'nextState' => $next?->scheduleState() ?? 'upcoming',
            'stateLabels' => [
                'overdue' => __('Overdue'),
                'today' => __('Due Today'),
                'completed' => __('Completed'),
                'upcoming' => __('Upcoming'),
            ],
            'today' => today()->toDateString(),
            'requiredNote' => __('Write a note first.'),
            'genericError' => __('Failed to save. Please try again.'),
            'savedNote' => __('Note saved.'),
            'stageSaved' => __('Stage updated.'),
            'wonNeedsQuotation' => __('WON needs an accepted quotation.'),
            'lostRequired' => __('Loss reason and note are required.'),
            'dateRequired' => __('Choose a date first.'),
        ];
    }

    public static function contactLinks(Lead $lead): array
    {
        $customer = $lead->customer;
        $project = $lead->project_name ?: __('the project');
        $message = __('Follow up on :project', ['project' => $project]);

        return [
            'whatsapp' => $customer?->waLink($message),
            'email' => $customer?->picEmail(),
            'phone' => $customer?->picPhone() ?: $customer?->phone,
            'contactUrl' => route('sales.my-leads.contact', $lead),
        ];
    }

    public static function nextStateClasses(string $state): string
    {
        return FollowUp::scheduleStateClasses($state);
    }
}
