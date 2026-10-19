<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $query = Partner::withCount('leads');

        if ($request->filled('search')) {
            $query->whereLike(['name', 'contact_person', 'phone'], $request->search);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $partners = $query->latest()->paginate(15)->withQueryString();

        return $request->ajax()
            ? view('partners._table', compact('partners'))->render()
            : view('partners.index', compact('partners'));
    }

    public function create()
    {
        return view('partners.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:supplier,vendor,kontraktor,partner,distributor',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        Partner::create($validated);

        return redirect()
            ->route('partners.index')
            ->with('success', __('Partner added successfully.'));
    }

    public function edit(Partner $partner)
    {
        return view('partners.edit', compact('partner'));
    }

    public function update(Request $request, Partner $partner)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:supplier,vendor,kontraktor,partner,distributor',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $partner->update($validated);

        return redirect()
            ->route('partners.index')
            ->with('success', __('Partner updated successfully.'));
    }

    public function destroy(Partner $partner)
    {
        $partner->delete();

        return redirect()
            ->route('partners.index')
            ->with('success', __('Partner deleted successfully.'));
    }
}