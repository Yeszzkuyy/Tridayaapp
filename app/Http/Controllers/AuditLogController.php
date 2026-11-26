<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'module' => ['nullable', 'string', 'max:64'],
            'action' => ['nullable', 'string', 'max:64'],
            'causer' => ['nullable', 'integer'],
        ]);

        $query = AuditLog::query()->latest('created_at');

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (filled($filters['module'] ?? null)) {
            $query->where('module', $filters['module']);
        }

        if (filled($filters['action'] ?? null)) {
            $query->where('action', $filters['action']);
        }

        if (filled($filters['causer'] ?? null)) {
            $query->where('causer_id', (int) $filters['causer']);
        }

        $logs = $query->paginate(20)->withQueryString();
        $modules = AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module');
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin-panel.audit-log', compact('logs', 'modules', 'actions', 'filters'));
    }
}
