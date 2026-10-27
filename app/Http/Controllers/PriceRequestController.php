<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PriceRequest;
use App\Models\PriceRequestComment;
use App\Models\PriceRequestReply;
use App\Models\User;
use App\Notifications\PriceRequestCommentNotification;
use App\Notifications\PriceRequestCompletedNotification;
use App\Notifications\PriceRequestCreatedNotification;
use App\Notifications\PriceRequestReviewedNotification;
use App\Support\PtAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Request Harga: sales/teknisi mengirim quotation, lalu Amir menangani
 * perhitungan, diskusi, dan revisi. Terpisah dari TechnicalRequest.
 */
class PriceRequestController extends Controller
{
    /** Jenis file yang boleh dilampirkan (quotation / balasan hitungan). */
    private const FILE_MIMES = 'pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,zip';

    public function create(Request $request)
    {
        $user = $request->user();
        // Form sama persis untuk sales & teknisi — hanya tombol kembali yang
        // menyesuaikan asal (inbox teknisi vs hub sales) agar terhubung.
        $isTechOnly = $user->can('manage-technician')
            && ! $user->can('manage-sales')
            && ! $user->can('manage-inside-sales');
        $backUrl = $isTechOnly
            ? route('teknisi.requests.index', ['tab' => 'price'])
            : route('sales.requests.index', ['tab' => 'price']);

        return view('sales.price-requests.create', [
            'customers' => $this->customerOptions($user),
            'backUrl' => $backUrl,
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

        $pricingEngineer = $this->configuredPricingEngineer();
        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $request->user()->id,
            'notes' => $validated['notes'] ?? null,
            'assigned_to' => $pricingEngineer?->id,
            'status' => $pricingEngineer ? 'in_progress' : 'waiting_lead',
        ]);

        $priceRequest->update([
            'quotation_path' => $this->storeFile($request->file('quotation'), 'price-requests/'.$priceRequest->id),
        ]);

        $this->addSystemComment(
            $priceRequest,
            $pricingEngineer
                ? __('Price request assigned to :name.', ['name' => $pricingEngineer->name])
                : __('Price request is waiting for a lead technician.'),
            ['event' => 'created', 'assigned_to' => $pricingEngineer?->id],
        );

        // Route directly to the configured pricing engineer. If that account
        // is unavailable, notify the lead technician group as a safe fallback.
        $recipients = $pricingEngineer
            ? collect([$pricingEngineer])
            : User::role('lead-technician')->get();
        $recipients = $recipients->reject(fn (User $recipient) => (int) $recipient->id === (int) $request->user()->id);
        Notification::send($recipients, new PriceRequestCreatedNotification($priceRequest));

        return redirect()->route('sales.price-requests.show', $priceRequest)
            ->with('success', __('Price request dikirim ke tim Engineer.'))
            ->with('success_card', true);
    }

    public function show(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        $priceRequest->load(['customer', 'requester.roles', 'assignee', 'replies.uploader', 'replies.acceptedBy']);

        $technicians = ($request->user()->hasRole('lead-technician') || $request->user()->hasRole('super-admin'))
            ? User::role('technician')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('sales.price-requests.show', compact('priceRequest', 'technicians'));
    }

    /** Sales pemilik (atau management) membatalkan request yang belum selesai. */
    public function cancel(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('cancel', $priceRequest);
        abort_if($priceRequest->isClosed(), 422);

        $priceRequest->update(['status' => 'cancelled']);
        $this->addSystemComment($priceRequest, __('Price request cancelled.'), ['event' => 'cancelled']);
        $priceRequest->loadMissing(['customer', 'assignee']);
        $this->notifyAssignedEngineer($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'cancelled'));

        return back()->with('success', __('Price request canceled.'))->with('success_card', true);
    }

    /** Fallback only: a lead technician may claim a request if Amir is unavailable. */
    public function take(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('take', $priceRequest);

        $priceRequest->update([
            'status' => 'in_progress',
            'assigned_to' => $request->user()->id,
        ]);
        $this->addSystemComment(
            $priceRequest,
            __(':name took responsibility for this price request.', ['name' => $request->user()->name]),
            ['event' => 'assigned', 'assigned_to' => $request->user()->id],
        );

        $this->notifyRequester($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'in_progress'));

        return back()->with('success', __('Price request diambil untuk dikerjakan.'))->with('success_card', true);
    }

    /** Fallback only: a lead technician may reject an unassigned request. */
    public function reject(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('reject', $priceRequest);

        $priceRequest->update(['status' => 'rejected']);
        $this->addSystemComment($priceRequest, __('The price request was rejected.'), ['event' => 'rejected']);

        $this->notifyRequester($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'rejected'));

        return back()->with('success', __('Price request ditolak.'))->with('success_card', true);
    }

    /** Fallback only: review requests when the configured pricing engineer is unavailable. */
    public function review(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        abort_unless($priceRequest->status === 'waiting_lead', 422);
        $this->authorize('review', $priceRequest);

        $validated = $request->validate(['decision' => 'required|in:accepted,rejected']);

        $priceRequest->update(['status' => $validated['decision']]);
        $this->addSystemComment(
            $priceRequest,
            __('Lead technician :decision the price request.', ['decision' => $validated['decision']]),
            ['event' => $validated['decision']],
        );
        $this->notifyRequester($priceRequest, new PriceRequestReviewedNotification($priceRequest, $validated['decision']));

        return back()->with('success', __('Price request telah di-' . $validated['decision'] . '.'))->with('success_card', true);
    }

    /** Fallback only: assign a technician when the configured pricing engineer is unavailable. */
    public function assign(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('assign', $priceRequest);
        abort_unless(in_array($priceRequest->status, ['accepted', 'assigned'], true), 422);

        $validated = $request->validate(['technician_id' => 'required|exists:users,id']);

        $technician = User::findOrFail($validated['technician_id']);
        abort_unless($technician->hasRole('technician'), 422, __('Only users with the Technician role can be assigned.'));

        $priceRequest->update([
            'assigned_to' => $technician->id,
            'status' => 'assigned',
        ]);
        $this->addSystemComment(
            $priceRequest,
            __('Price request assigned to :name.', ['name' => $technician->name]),
            ['event' => 'assigned', 'assigned_to' => $technician->id],
        );

        $this->notifyRequester($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'assigned'));

        return back()->with('success', __('Technician assigned successfully.'))->with('success_card', true);
    }

    /** Upload a new immutable calculation version and return the request to Sales review. */
    public function reply(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('reply', $priceRequest);

        $validated = $request->validate([
            'reply' => 'required|file|max:10240|mimes:'.self::FILE_MIMES,
            'reply_notes' => 'nullable|string|max:2000',
        ]);

        $storedPath = null;

        try {
            DB::transaction(function () use ($request, $priceRequest, $validated, &$storedPath): void {
                $lockedRequest = PriceRequest::query()->lockForUpdate()->findOrFail($priceRequest->id);
                $this->authorize('reply', $lockedRequest);
                abort_unless(in_array($lockedRequest->status, ['in_progress', 'revision', 'accepted', 'assigned'], true), 422);

                $version = ((int) $lockedRequest->replies()->max('version')) + 1;
                $storedPath = $this->storeFile(
                    $request->file('reply'),
                    'price-requests/'.$lockedRequest->id.'/replies',
                    $version,
                );

                $reply = $lockedRequest->replies()->create([
                    'uploaded_by' => $request->user()->id,
                    'version' => $version,
                    'file_path' => $storedPath,
                    'notes' => $validated['reply_notes'] ?? null,
                ]);

                $lockedRequest->update([
                    'reply_path' => $reply->file_path,
                    'reply_notes' => $reply->notes,
                    'status' => 'waiting_sales_review',
                    'completed_at' => null,
                ]);

                $this->addSystemComment(
                    $lockedRequest,
                    __('Price calculation version :version uploaded.', ['version' => $version]),
                    ['event' => 'reply_uploaded', 'reply_id' => $reply->id, 'version' => $version],
                );
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('private')->delete($storedPath);
            }

            throw $exception;
        }

        $priceRequest->refresh()->load(['customer', 'requester']);

        $this->notifyRequester($priceRequest, new PriceRequestCompletedNotification($priceRequest));

        return back()->with('success', __('Price calculation sent to Sales for review.'))->with('success_card', true);
    }

    /** Sales accepts the latest calculation as the final price. */
    public function acceptReply(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('decide', $priceRequest);

        DB::transaction(function () use ($request, $priceRequest): void {
            $lockedRequest = PriceRequest::query()->lockForUpdate()->findOrFail($priceRequest->id);
            $this->authorize('decide', $lockedRequest);
            abort_unless($lockedRequest->status === 'waiting_sales_review', 422);

            $reply = $lockedRequest->replies()
                ->reorder()
                ->lockForUpdate()
                ->orderByDesc('version')
                ->firstOrFail();
            abort_if($reply->accepted_at !== null, 422);

            $acceptedAt = now();
            $reply->update([
                'accepted_by' => $request->user()->id,
                'accepted_at' => $acceptedAt,
            ]);
            $lockedRequest->update([
                'status' => 'completed',
                'completed_at' => $acceptedAt,
            ]);

            $this->addSystemComment(
                $lockedRequest,
                __('Sales accepted pricing version :version.', ['version' => $reply->version]),
                ['event' => 'accepted', 'reply_id' => $reply->id, 'version' => $reply->version],
            );
        });

        $priceRequest->refresh()->load(['customer', 'assignee']);
        $this->notifyAssignedEngineer($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'accepted'));

        return back()->with('success', __('Price accepted as final.'))->with('success_card', true);
    }

    /** Sales asks the assigned engineer to revise/renegotiate the latest calculation. */
    public function requestRevision(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('decide', $priceRequest);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $priceRequest, $validated): void {
            $lockedRequest = PriceRequest::query()->lockForUpdate()->findOrFail($priceRequest->id);
            $this->authorize('decide', $lockedRequest);
            abort_unless($lockedRequest->status === 'waiting_sales_review', 422);

            $reply = $lockedRequest->replies()
                ->reorder()
                ->lockForUpdate()
                ->orderByDesc('version')
                ->firstOrFail();
            abort_if($reply->accepted_at !== null, 422);

            $lockedRequest->update([
                'status' => 'revision',
                'completed_at' => null,
            ]);
            $lockedRequest->comments()->create([
                'user_id' => $request->user()->id,
                'body' => $validated['reason'],
                'type' => PriceRequestComment::TYPE_MESSAGE,
                'meta' => ['event' => 'revision_request', 'reply_id' => $reply->id, 'version' => $reply->version],
            ]);
            $this->addSystemComment(
                $lockedRequest,
                __('Revision requested for pricing version :version.', ['version' => $reply->version]),
                ['event' => 'revision_requested', 'reply_id' => $reply->id, 'version' => $reply->version],
            );

        });

        $priceRequest->refresh()->load(['customer', 'assignee']);
        $this->notifyAssignedEngineer($priceRequest, new PriceRequestReviewedNotification($priceRequest, 'revision'));

        return back()->with('success', __('Revision request sent to the Pricing Engineer.'))->with('success_card', true);
    }

    public function downloadQuotation(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        abort_unless($priceRequest->quotation_path && Storage::disk('private')->exists($priceRequest->quotation_path), 404);

        return Storage::disk('private')->download($priceRequest->quotation_path);
    }

    public function downloadReply(Request $request, PriceRequest $priceRequest)
    {
        $this->authorize('view', $priceRequest);
        abort_unless($priceRequest->reply_path && Storage::disk('private')->exists($priceRequest->reply_path), 404);

        return Storage::disk('private')->download($priceRequest->reply_path);
    }

    public function downloadReplyVersion(Request $request, PriceRequest $priceRequest, PriceRequestReply $reply)
    {
        $this->authorize('view', $priceRequest);
        abort_unless((int) $reply->price_request_id === (int) $priceRequest->id, 404);
        abort_unless(Storage::disk('private')->exists($reply->file_path), 404);

        return Storage::disk('private')->download($reply->file_path);
    }

    private function configuredPricingEngineer(): ?User
    {
        $email = strtolower(trim((string) config('services.price_request.pricing_engineer_email')));
        if ($email === '') {
            return null;
        }

        return User::role('lead-technician')->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    private function addSystemComment(PriceRequest $priceRequest, string $body, array $meta = []): PriceRequestComment
    {
        return $priceRequest->comments()->create([
            'user_id' => null,
            'body' => $body,
            'type' => PriceRequestComment::TYPE_SYSTEM,
            'meta' => $meta,
        ]);
    }

    private function notifyAssignedEngineer(PriceRequest $priceRequest, \Illuminate\Notifications\Notification $notification): void
    {
        $engineer = $priceRequest->assignee ?? $this->configuredPricingEngineer();
        if ($engineer && (int) $engineer->id !== (int) auth()->id()) {
            $engineer->notify($notification);
        }
    }

    /** Kirim notifikasi ke sales peminta — lewati bila dia sendiri yang beraksi. */
    private function notifyRequester(PriceRequest $priceRequest, \Illuminate\Notifications\Notification $notification): void
    {
        $requester = $priceRequest->requester;
        if ($requester && (int) $requester->id !== (int) auth()->id()) {
            $requester->notify($notification);
        }
    }

    private function storeFile($file, string $dir, ?int $version = null): string
    {
        $prefix = $version !== null ? 'v'.$version.'_' : '';

        return $file->storeAs(
            $dir,
            $prefix.time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()),
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
