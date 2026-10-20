<?php

namespace App\Http\Controllers;

use App\Models\PriceRequest;
use App\Models\TechnicalRequest;
use Illuminate\Http\Request;

/**
 * Inbox permintaan yang masuk dari sales untuk tim teknisi:
 * Request Harga (price_requests) dan Request Tim Engineer (technical_requests).
 */
class TeknisiRequestController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab') === 'engineer' ? 'engineer' : 'price';

        $priceRequests = PriceRequest::with(['customer', 'requester', 'assignee'])
            ->latest()
            ->paginate(15, ['*'], 'price_page')
            ->withQueryString();

        $engineerRequests = TechnicalRequest::with(['lead.customer', 'requester', 'technician'])
            ->where('request_type', '!=', 'estimate')
            ->latest()
            ->paginate(15, ['*'], 'engineer_page')
            ->withQueryString();

        return view('teknisi.requests.index', [
            'tab' => $tab,
            'priceRequests' => $priceRequests,
            'engineerRequests' => $engineerRequests,
            'openCounts' => [
                'price' => PriceRequest::whereIn('status', ['waiting_lead', 'in_progress'])->count(),
                'engineer' => TechnicalRequest::where('request_type', '!=', 'estimate')
                    ->whereIn('status', ['waiting_lead', 'accepted', 'assigned', 'in_progress'])
                    ->count(),
            ],
        ]);
    }
}
