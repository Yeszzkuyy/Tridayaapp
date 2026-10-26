<?php

namespace App\Http\Controllers;

use App\Services\DevWorkLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DevWorkLogController extends Controller
{
    protected DevWorkLogService $service;

    public function __construct(DevWorkLogService $service)
    {
        $this->service = $service;
    }

    /**
     * Tampilkan halaman generator rekap pekerjaan.
     */
    public function index(Request $request): View
    {
        $days = (int) $request->input('days', 2);
        if ($days <= 0 || $days > 30) {
            $days = 2;
        }

        $commits = $this->service->getRecentCommits($days);
        $summaryText = $this->service->generateWhatsAppSummary($days);

        return view('developer.worklog', [
            'days' => $days,
            'commits' => $commits,
            'summaryText' => $summaryText,
        ]);
    }

    /**
     * Refresh atau buat ulang summary berdasarkan input kustom.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:30',
            'bukti' => 'nullable|string|max:1000',
            'kendala' => 'nullable|string|max:1000',
            'next_steps' => 'nullable|string|max:1000',
        ]);

        $days = (int) ($validated['days'] ?? 2);
        $summaryText = $this->service->generateWhatsAppSummary($days, $validated);

        return response()->json([
            'status' => 'success',
            'summary' => $summaryText,
            'commits' => $this->service->getRecentCommits($days),
        ]);
    }
}
