<?php

namespace App\Http\Controllers;

use App\Models\PriceRequest;
use App\Models\TechnicalRequest;
use Illuminate\Http\Request;

/**
 * Inbox permintaan untuk tim teknisi (sama persis & terhubung dengan
 * Request Price sales): Request Harga (price_requests) dan Request Tim
 * Engineer (technical_requests). Teknisi juga bisa membuat Price Request
 * sendiri via tombol New Request (form sama persis, file PDF).
 */
class TeknisiRequestController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab') === 'engineer' ? 'engineer' : 'price';
        $user = $request->user();
        $canSeeFullInbox = $user->hasRole('lead-technician')
            || $user->hasRole('super-admin')
            || $user->can('manage-sales-leads');

        $priceQuery = PriceRequest::with(['customer', 'requester', 'assignee'])->latest();
        $openPriceQuery = PriceRequest::whereIn('status', ['waiting_lead', 'accepted', 'assigned', 'in_progress', 'revision', 'waiting_sales_review']);
        if (! $canSeeFullInbox) {
            $scopeToParticipant = fn ($query) => $query->where(function ($participant) use ($user) {
                $participant->where('requested_by', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
            $scopeToParticipant($priceQuery);
            $scopeToParticipant($openPriceQuery);
        }

        $priceRequests = $priceQuery->paginate(15, ['*'], 'price_page')->withQueryString();

        $engineerRequests = TechnicalRequest::with(['lead.customer', 'requester', 'technician'])
            ->where('request_type', '!=', 'estimate')
            ->when(! $canSeeFullInbox, function ($query) use ($user) {
                // Teknisi biasa hanya melihat request yang ditugaskan
                // kepadanya (atau yang ia buat sendiri). Inbox penuh
                // (waiting_lead) hanya untuk koordinator teknis.
                $query->where(function ($query) use ($user) {
                    $query->where('assigned_technician_id', $user->id)
                        ->orWhere('requested_by', $user->id);
                });
            })
            ->latest()
            ->paginate(15, ['*'], 'engineer_page')
            ->withQueryString();

        $openEngineerQuery = TechnicalRequest::where('request_type', '!=', 'estimate')
            ->whereIn('status', ['waiting_lead', 'accepted', 'assigned', 'in_progress'])
            ->when(! $canSeeFullInbox, function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('assigned_technician_id', $user->id)
                        ->orWhere('requested_by', $user->id);
                });
            });

        return view('teknisi.requests.index', [
            'tab' => $tab,
            'priceRequests' => $priceRequests,
            'engineerRequests' => $engineerRequests,
            'openCounts' => [
                'price' => $openPriceQuery->count(),
                'engineer' => $openEngineerQuery->count(),
            ],
        ]);
    }
}
