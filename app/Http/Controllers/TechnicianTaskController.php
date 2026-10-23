<?php

namespace App\Http\Controllers;

use App\Models\TechnicalRequest;
use Illuminate\Http\Request;

/**
 * "Tugas Saya" — daftar pekerjaan yang ditugaskan Lead Technician kepada
 * teknisi yang sedang login. Hanya request miliknya sendiri yang tampil;
 * detailnya memakai halaman detail request yang sama dalam mode read-only.
 */
class TechnicianTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        abort_unless($user?->hasRole('technician'), 403);

        $query = TechnicalRequest::with(['lead.customer', 'requester', 'technician'])
            ->where('assigned_technician_id', $user->id)
            ->where('request_type', '!=', 'estimate');

        if (in_array($request->get('status'), TechnicalRequest::STATUSES, true)) {
            $query->where('status', $request->get('status'));
        }

        $search = trim((string) $request->get('q'));
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('lead.customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
            });
        }

        $tasks = $query->latest()->paginate(15)->withQueryString();

        return view('teknisi.tasks.index', compact('tasks'));
    }

    public function show(TechnicalRequest $technicalRequest)
    {
        $user = auth()->user();

        // Kunci akses ke teknisi yang BENAR-BENAR sedang ditugaskan. Kalau
        // tugas dipindahkan ke teknisi lain, pemegang lama otomatis kehilangan
        // akses karena kolom assigned_technician_id sudah berubah.
        abort_unless(
            $user && (int) $technicalRequest->assigned_technician_id === (int) $user->id,
            403
        );

        // 'estimate' sudah punya alur Request Harga sendiri, jadi tidak boleh
        // dibuka lewat Tugas Saya.
        abort_if($technicalRequest->request_type === 'estimate', 404);

        $technicalRequest->load(['lead.customer', 'requester', 'technician']);

        $technicians = collect();
        $fromTasks = true;

        return view('sales.technical-requests.show', compact('technicalRequest', 'technicians', 'fromTasks'));
    }
}
