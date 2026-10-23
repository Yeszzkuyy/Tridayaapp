<?php

namespace App\Http\Controllers;

use App\Models\TechnicalRequest;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * "Tugas Saya" — daftar pekerjaan yang ditugaskan Lead Technician kepada
 * teknisi yang sedang login. Super-admin mendapat tampilan penuh: semua
 * tugas teknisi, termasuk yang belum di-assign, beserta seluruh aksinya.
 */
class TechnicianTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user?->hasRole('super-admin') ?? false;

        abort_unless($isSuperAdmin || $user?->hasRole('technician'), 403);

        $query = TechnicalRequest::with(['lead.customer', 'requester', 'technician'])
            ->where('request_type', '!=', 'estimate');

        if ($isSuperAdmin) {
            // Super-admin melihat semua tugas. Filter teknisi opsional,
            // termasuk tugas yang belum di-assign.
            $technicianFilter = $request->get('technician_id');

            if ($technicianFilter === 'unassigned') {
                $query->whereNull('assigned_technician_id');
            } elseif ($technicianFilter !== null && $technicianFilter !== '' && ctype_digit((string) $technicianFilter)) {
                $query->where('assigned_technician_id', (int) $technicianFilter);
            }
        } else {
            $query->where('assigned_technician_id', $user->id);
        }

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
        $technicians = $isSuperAdmin
            ? User::role('technician')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('teknisi.tasks.index', compact('tasks', 'isSuperAdmin', 'technicians'));
    }

    public function show(TechnicalRequest $technicalRequest)
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->hasRole('super-admin') ?? false;

        // Teknisi hanya boleh membuka tugas yang BENAR-BENAR sedang
        // ditugaskan kepadanya. Kalau tugas dipindahkan ke teknisi lain,
        // pemegang lama otomatis kehilangan akses. Super-admin melihat semua.
        abort_unless(
            $user && ($isSuperAdmin || (int) $technicalRequest->assigned_technician_id === (int) $user->id),
            403
        );

        // 'estimate' sudah punya alur Request Harga sendiri, jadi tidak boleh
        // dibuka lewat Tugas Saya.
        abort_if($technicalRequest->request_type === 'estimate', 404);

        $technicalRequest->load(['lead.customer', 'requester', 'technician']);

        $technicians = $isSuperAdmin
            ? User::role('technician')->orderBy('name')->get(['id', 'name'])
            : collect();
        $fromTasks = true;
        $readOnly = ! $isSuperAdmin;

        return view('sales.technical-requests.show', compact('technicalRequest', 'technicians', 'fromTasks', 'readOnly'));
    }
}
