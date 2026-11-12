<?php

namespace App\Http\Controllers;

use App\Models\LeadTask;
use App\Models\Proposal;
use App\Notifications\QuotationGeneratedNotification;
use App\Services\QuotationNumberService;
use App\Services\QuotationPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InsideSalesQuotationController extends Controller
{
    public function __construct(private QuotationNumberService $numbers) {}
    public function create(LeadTask $leadTask)
    {
        $this->authorizeTask($leadTask);

        $leadTask->load(['lead.customer', 'lead.assignee', 'items.product']);

        abort_if($leadTask->items->isEmpty(), 422, __('Add at least one quotation item before creating a quotation.'));

        if ($leadTask->proposal()->exists()) {
            return redirect()
                ->route('lead-tasks.show', $leadTask)
                ->with('success', __('This task already has a quotation.'));
        }

        $customer = $leadTask->lead?->customer;
        $previewPt = strtoupper((string) ($customer?->pt_group ?? $leadTask->lead?->pt_group ?? 'WANI'));

        return view('inside-sales.quotations.create', [
            'task' => $leadTask,
            'items' => $leadTask->items,
            'companies' => array_keys(config('companies')),
            'prefill' => [
                'proposal_number' => $this->numbers->generate($previewPt, $leadTask->lead?->assignee ?? auth()->user()),
                'proposal_date' => today()->toDateString(),
                'customer' => $customer?->name ?? '-',
                'customer_address' => $customer?->billingAddress(),
                'contact_name' => $customer?->picName(),
                'contact_email' => $customer?->picEmail(),
                'contact_phone' => $customer?->picPhone(),
                'project_description' => $leadTask->lead?->kebutuhan ?: $leadTask->description,
                'payment_terms_text' => '',
                'letterhead_pt' => $previewPt,
            ],
        ]);
    }

    public function store(Request $request, LeadTask $leadTask, QuotationPdfService $pdfService)
    {
        $this->authorizeTask($leadTask);

        $companies = array_keys(config('companies'));

        $validated = $request->validate([
            'proposal_date' => 'required|date',
            'customer' => 'required|string|max:255',
            'customer_address' => 'nullable|string|max:2000',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'project_description' => 'nullable|string|max:3000',
            'payment_terms_text' => 'nullable|string|max:255',
            'letterhead_pt' => 'required|in:'.implode(',', $companies),
        ]);

        $items = $leadTask->items()->get();
        abort_if($items->isEmpty(), 422, __('Add at least one quotation item before creating a quotation.'));
        abort_if(
            $leadTask->proposal()->exists(),
            422,
            __('This task already has a quotation.')
        );
        abort_if(
            $leadTask->lead?->hasActiveProposal(),
            422,
            __('This lead already has a quotation. Revise the existing quotation instead of creating a new one.')
        );

        $salesOwner = $leadTask->lead?->assignee ?? $leadTask->creator;
        $proposalNumber = $this->numbers->generate($validated['letterhead_pt'], $salesOwner ?? auth()->user());

        $proposal = DB::transaction(function () use ($leadTask, $items, $validated, $pdfService, $salesOwner, $proposalNumber) {
            $proposal = Proposal::create([
                'lead_id' => $leadTask->lead_id,
                'lead_task_id' => $leadTask->id,
                'proposal_number' => $proposalNumber,
                'created_by' => $salesOwner?->id ?? auth()->id(),
                'proposal_date' => $validated['proposal_date'],
                'customer' => $validated['customer'],
                'customer_address' => $validated['customer_address'] ?? null,
                'contact_name' => $validated['contact_name'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'project_description' => $validated['project_description'] ?? null,
                'payment_terms_text' => $validated['payment_terms_text'] ?? null,
                'letterhead_pt' => $validated['letterhead_pt'],
                'status' => 'draft',
                'internal_notes' => __('Created from Inside Sales task #:task.', ['task' => $leadTask->id]),
            ]);

            foreach ($items as $item) {
                $proposal->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'scope_of_supply' => $item->scope_of_supply,
                    'lead_time' => $item->lead_time,
                    'section' => $item->section ?: 'MATERIAL',
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $item->unit_cost,
                    'discount' => $item->discount,
                    'subtotal' => $item->subtotal,
                ]);
            }

            $proposal->recalculate();
            $proposal->forceFill([
                'tax' => round((float) $proposal->subtotal * 0.12, 2),
            ])->save();
            $proposal->recalculate();

            $pdfService->generate($proposal);

            $leadTask->update(['status' => 'done']);

            return $proposal;
        });

        if ($salesOwner && (int) $salesOwner->id !== (int) auth()->id()) {
            try {
                $salesOwner->notify(new QuotationGeneratedNotification($proposal));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Quotation :number and PDF created successfully.', ['number' => $proposal->proposal_number]))
            ->with('success_card', true);
    }

    public function pdf(LeadTask $leadTask)
    {
        $this->authorizeTask($leadTask);

        $proposal = $leadTask->proposal;
        abort_unless($proposal && $proposal->pdf_path, 404, __('Quotation PDF not found.'));
        abort_unless(Storage::disk('private')->exists($proposal->pdf_path), 404, __('Quotation PDF not found.'));

        return Storage::disk('private')->download(
            $proposal->pdf_path,
            preg_replace('/[^A-Za-z0-9._-]/', '_', $proposal->proposal_number).'.pdf'
        );
    }

    private function authorizeTask(LeadTask $task): void
    {
        $user = auth()->user();

        abort_unless(
            $user
            && $user->can('manage-inside-sales')
            && (int) $task->assigned_to === (int) $user->id,
            403
        );
    }
}
