<?php

namespace App\Http\Controllers;

use App\Models\RequestHarga;
use App\Models\Project;
use Illuminate\Http\Request;

class RequestHargaController extends Controller
{
    public function index(Request $request)
    {
        $query = RequestHarga::with('project.customer')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $requestHargas = $query->paginate(15)->withQueryString();
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.request-hargas.index', compact('requestHargas', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);
        $selectedProject = $request->query('project_id');

        return view('teknisi.request-hargas.create', compact('projects', 'selectedProject'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'items' => 'nullable|array',
            'items.*.device' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.specification' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:draft,submitted,approved,rejected,completed',
        ]);

        RequestHarga::create($validated);

        return redirect()->route('teknisi.request-hargas.index')
            ->with('success', 'Request Harga berhasil ditambahkan.');
    }

    public function show(RequestHarga $requestHarga)
    {
        $requestHarga->load('project.customer');

        return view('teknisi.request-hargas.show', compact('requestHarga'));
    }

    public function edit(RequestHarga $requestHarga)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.request-hargas.edit', compact('requestHarga', 'projects'));
    }

    public function update(Request $request, RequestHarga $requestHarga)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'items' => 'nullable|array',
            'items.*.device' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.specification' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:draft,submitted,approved,rejected,completed',
        ]);

        $requestHarga->update($validated);

        return redirect()->route('teknisi.request-hargas.show', $requestHarga)
            ->with('success', 'Request Harga berhasil diupdate.');
    }

    public function destroy(RequestHarga $requestHarga)
    {
        $requestHarga->delete();

        return redirect()->route('teknisi.request-hargas.index')
            ->with('success', 'Request Harga berhasil dihapus.');
    }
}
