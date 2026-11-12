<?php

namespace App\Http\Controllers;

use App\Models\LeadTask;
use App\Models\ProductPriceLog;
use App\Models\Proposal;
use App\Notifications\QuotationGeneratedNotification;
use App\Services\QuotationPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InsideSalesQuotationController extends Controller
{
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
        $deliveryTerms = $this->stockSummary($leadTask->items);
        $warrantyTerms = $this->warrantySummary($leadTask->items);

        return view('inside-sales.quotations.create', [
            'task' => $leadTask,
            'items' => $leadTask->items,
            'companies' => array_keys(config('companies')),
            'prefill' => [
                'proposal_number' => '',
                'proposal_date' => today()->toDateString(),
                'customer' => $customer?->name ?? '-',
                'customer_address' => $customer?->billingAddress(),
                'contact_name' => $customer?->picName(),
                'contact_email' => $customer?->picEmail(),
                'contact_phone' => $customer?->picPhone(),
                'project_description' => $leadTask->lead?->kebutuhan ?: $leadTask->description,
                'payment_terms_text' => '',
                'delivery_terms' => $deliveryTerms,
                'warranty_terms' => $warrantyTerms,
                'letterhead_pt' => strtoupper((string) ($customer?->pt_group ?? $leadTask->lead?->pt_group ?? 'WANI')),
            ],
        ]);
    }

    /** Tarik harga modal + stok + garansi terbaru dari price request ke item task. */
    public function pullPrices(LeadTask $leadTask)
    {
        $this->authorizeTask($leadTask);

        $leadTask->load(['lead.customer', 'items']);
        $customerId = $leadTask->lead?->customer_id;
        $updated = 0;
        $missing = 0;

        foreach ($leadTask->items as $item) {
            if (! $item->product_id) {
                $missing++;
                continue;
            }

            $query = ProductPriceLog::query()
                ->where('product_id', $item->product_id)
                ->where('price_type', 'cost');

            $log = $customerId
                ? (clone $query)
                    ->whereNotNull('estimator_request_item_id')
                    ->whereHas('estimatorRequestItem.estimatorRequest', fn ($request) => $request->where('customer_id', $customerId))
                    ->latest('created_at')
                    ->latest('id')
                    ->first()
                : null;

            $log ??= (clone $query)->whereNotNull('estimator_request_item_id')->latest('created_at')->latest('id')->first();
            $log ??= (clone $query)->latest('created_at')->latest('id')->first();

            if (! $log) {
                $missing++;
                continue;
            }

            $item->update([
                'product_price_log_id' => $log->id,
                'unit_cost' => $log->price,
                'stock_status' => $log->stock_status ?: $item->stock_status,
                'stock_eta' => $log->stock_eta ?: $item->stock_eta,
                'warranty_info' => $log->warranty_info ?: $item->warranty_info,
            ]);
            $updated++;
        }

        return back()->with(
            'success',
            __(':updated items updated from the latest price. :missing items have no price yet.', [
                'updated' => $updated,
                'missing' => $missing,
            ])
        )->with('success_card', true);
    }

    public function store(Request $request, LeadTask $leadTask, QuotationPdfService $pdfService)
    {
        $this->authorizeTask($leadTask);

        $companies = array_keys(config('companies'));

        $validated = $request->validate([
            'proposal_number' => 'required|string|max:100|unique:proposals,proposal_number',
            'proposal_date' => 'required|date',
            'customer' => 'required|string|max:255',
            'customer_address' => 'nullable|string|max:2000',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'project_description' => 'nullable|string|max:3000',
            'payment_terms_text' => 'nullable|string|max:255',
            'delivery_terms' => 'nullable|string|max:255',
            'warranty_terms' => 'nullable|string|max:255',
            'letterhead_pt' => 'required|in:'.implode(',', $companies),
        ]);

        $items = $leadTask->items()->get();
        abort_if($items->isEmpty(), 422, __('Add at least one quotation item before creating a quotation.'));
        abort_if(
            $leadTask->proposal()->exists(),
            422,
            __('This task already has a quotation.')
        );

        $deliveryTerms = $validated['delivery_terms'] ?? $this->stockSummary($items);
        $warrantyTerms = $validated['warranty_terms'] ?? $this->warrantySummary($items);
        $salesOwner = $leadTask->lead?->assignee ?? $leadTask->creator;

        $proposal = DB::transaction(function () use ($leadTask, $items, $validated, $pdfService, $salesOwner, $deliveryTerms, $warrantyTerms) {
            $proposal = Proposal::create([
                'lead_id' => $leadTask->lead_id,
                'lead_task_id' => $leadTask->id,
                'proposal_number' => $validated['proposal_number'],
                'created_by' => $salesOwner?->id ?? auth()->id(),
                'proposal_date' => $validated['proposal_date'],
                'customer' => $validated['customer'],
                'customer_address' => $validated['customer_address'] ?? null,
                'contact_name' => $validated['contact_name'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'project_description' => $validated['project_description'] ?? null,
                'payment_terms_text' => $validated['payment_terms_text'] ?? null,
                'delivery_terms' => $deliveryTerms,
                'warranty_terms' => $warrantyTerms,
                'letterhead_pt' => $validated['letterhead_pt'],
                'status' => 'draft',
                'internal_notes' => __('Created from Inside Sales task #:task.', ['task' => $leadTask->id]),
            ]);

            foreach ($items as $item) {
                $proposal->items()->create([
                    'product_id' => $item->product_id,
                    'product_price_log_id' => $item->product_price_log_id,
                    'description' => $item->description,
                    'scope_of_supply' => $item->scope_of_supply,
                    'lead_time' => $item->lead_time,
                    'stock_status' => $item->stock_status,
                    'stock_eta' => $item->stock_eta,
                    'warranty_info' => $item->warranty_info,
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

    private function stockSummary($items): ?string
    {
        $groups = $items
            ->filter(fn ($item) => filled($item->stock_status))
            ->groupBy('stock_status');

        if ($groups->isEmpty()) {
            return null;
        }

        return $groups->map(function ($group, $status): string {
            $label = $status === 'indent' ? __('Indent') : __('Ready Stock');
            $eta = $group->pluck('stock_eta')->filter()->unique()->implode(', ');

            return $eta !== '' ? $label.' '.$eta : $label;
        })->values()->implode('; ');
    }

    private function warrantySummary($items): ?string
    {
        $warranty = $items->pluck('warranty_info')->filter()->unique()->implode('; ');

        return $warranty !== '' ? $warranty : null;
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
