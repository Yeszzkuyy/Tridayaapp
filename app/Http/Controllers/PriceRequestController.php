<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PriceRequest;
use App\Models\User;
use App\Notifications\PriceRequestCompletedNotification;
use App\Notifications\PriceRequestCreatedNotification;
use App\Notifications\PriceRequestReviewedNotification;
use App\Support\PtAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Request Harga: sales kirim file Quotation + customer (PT) + catatan;
 * tim engineer (Lead Teknisi) mengambil/menolak lalu membalas dengan file
 * hasil hitungan. Terpisah dari TechnicalRequest (Request Tim Engineer).
 */
class PriceRequestController extends Controller
{
    /** Jenis file yang boleh dilampirkan (quotation / balasan hitungan). */
    private const FILE_MIMES = 'pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,zip';

    public function create(Request $request)
    {
        return view('sales.price-requests.create', [
            'customers' => $this->customerOptions($request->user()),
            'backUrl' => route('sales.requests.index', ['tab' => 'price']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'notes' => 'nullable|string|max:2000',
            'quotation' => 'required|file|max:10240|mimes:'.self::FILE_MIMES,
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        abort_unless(PtAccess::canWritePt($request->user(), $customer->pt_group), 403);

        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $request->user()->id,
            'notes' => $validated['notes'] ?? null,
            'status' => 'waiting_lead',
        ]);

        $priceRequest->update([
            'quotation_path' => $this->storeFile($request->file('quotation'), 'price-requests/'.$priceRequest->id),
        ]);

        Notification::send(
            User::role('lead-technician')->get(),
            new PriceRequestCreatedNotification($priceRequest)
        );

        return redirect()->route('sales.price-requests.show', $priceRequest)
            ->with('success', __('Price request dikirim ke tim Engineer.'));
    }

    public function show(Request $request, PriceRequest $priceRequest)
    {
        $priceRequest->load(['customer', 'requester', 'assignee']);
        $this->authorizeView($request->user(), $priceRequest);

        return view('sales.price-requests.show', compact('priceRequest'));
    }

    /** Sales pemilik (atau management) membatalkan request yang belum selesai. */
    public function cancel(Request $request, PriceRequest $priceRequest)
    {
        $user = $request->user();
        abort_unless(
            (int) $priceRequest->requested_by === (int) $user->id || $user->can('manage-sales-leads'),
            403
        );
        abort_if($priceRequest->isClosed(), 422);

        $priceRequest->update(['status' => 'cancelled']);

        return back()->with('success', __('Price request canceled.'));
    }

    /** Lead Teknisi/Teknisi mengambil request untuk dikerjakan. */
    public function take(Request $request, PriceRequest $priceRequest)
    {
        abort_unless($request->user()->can('manage-technician'), 403);
        abort_if($priceRequest->isClosed(), 422);

        $priceRequest->update([
            'status' => 'in_progress',
            'assigned_to' => $request->user()->id,
        ]);

        $priceRequest->requester?->notify(new PriceRequestReviewedNotification($priceRequest, 'in_progress'));

        return back()->with('success', __('Price request diambil untuk dikerjakan.'));
    }

    /** Lead Teknisi menolak request (mis. quotation tidak jelas). */
    public function reject(Request $request, PriceRequest $priceRequest)
    {
        abort_unless($request->user()->can('manage-technician'), 403);
        abort_if($priceRequest->isClosed(), 422);

        $priceRequest->update(['status' => 'rejected']);

        $priceRequest->requester?->notify(new PriceRequestReviewedNotification($priceRequest, 'rejected'));

        return back()->with('success', __('Price request ditolak.'));
    }

    /** Balas request dengan file hasil hitungan → status selesai. */
    public function reply(Request $request, PriceRequest $priceRequest)
    {
        abort_unless($request->user()->can('manage-technician'), 403);
        abort_unless(in_array($priceRequest->status, ['waiting_lead', 'in_progress'], true), 422);

        $validated = $request->validate([
            'reply' => 'required|file|max:10240|mimes:'.self::FILE_MIMES,
            'reply_notes' => 'nullable|string|max:2000',
        ]);

        $priceRequest->update([
            'reply_path' => $this->storeFile($request->file('reply'), 'price-requests/'.$priceRequest->id.'/reply'),
            'reply_notes' => $validated['reply_notes'] ?? null,
            'status' => 'completed',
            'assigned_to' => $priceRequest->assigned_to ?? $request->user()->id,
            'completed_at' => now(),
        ]);

        $priceRequest->requester?->notify(new PriceRequestCompletedNotification($priceRequest));

        return back()->with('success', __('Balasan harga terkirim ke sales.'));
    }

    public function downloadQuotation(Request $request, PriceRequest $priceRequest)
    {
        $this->authorizeView($request->user(), $priceRequest);
        abort_unless($priceRequest->quotation_path && Storage::disk('private')->exists($priceRequest->quotation_path), 404);

        return Storage::disk('private')->download($priceRequest->quotation_path);
    }

    public function downloadReply(Request $request, PriceRequest $priceRequest)
    {
        $this->authorizeView($request->user(), $priceRequest);
        abort_unless($priceRequest->reply_path && Storage::disk('private')->exists($priceRequest->reply_path), 404);

        return Storage::disk('private')->download($priceRequest->reply_path);
    }

    private function authorizeView(User $user, PriceRequest $priceRequest): void
    {
        if ((int) $priceRequest->requested_by === (int) $user->id
            || (int) $priceRequest->assigned_to === (int) $user->id
            || $user->can('manage-technician')
            || $user->can('manage-sales-leads')) {
            return;
        }

        abort(403);
    }

    private function storeFile($file, string $dir): string
    {
        return $file->storeAs(
            $dir,
            time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()),
            'private'
        );
    }

    /**
     * Customer yang boleh dipilih user ini: PT dari role sales-* plus
     * customer tanpa PT (general). Tanpa role PT = semua.
     */
    private function customerOptions(User $user): array
    {
        $pts = PtAccess::userPts($user);

        return Customer::query()
            ->when($pts !== [], fn ($q) => $q->where(
                fn ($w) => $w->whereIn('pt_group', $pts)->orWhereNull('pt_group')
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'pt_group'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->pt_group ? " ({$c->pt_group})" : '')])
            ->all();
    }
}
