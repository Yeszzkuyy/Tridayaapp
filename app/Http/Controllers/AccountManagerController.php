<?php

namespace App\Http\Controllers;

use App\Models\AccountManager;
use Illuminate\Http\Request;

class AccountManagerController extends Controller
{

    public function index()
    {
        $accountManagers = AccountManager::latest()->get();
        return view('admin-panel.account-managers.index', compact('accountManagers'));
    }

    public function create()
    {
        return view('admin-panel.account-managers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        AccountManager::create($validated);

        return redirect()
            ->route('admin-panel.account-managers.index')
            ->with('success', __('Account Manager added successfully.'));
    }

    public function edit(AccountManager $accountManager)
    {
        return view('admin-panel.account-managers.edit', compact('accountManager'));
    }

    public function update(Request $request, AccountManager $accountManager)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $accountManager->update($validated);

        return redirect()
            ->route('admin-panel.account-managers.index')
            ->with('success', __('Account Manager updated successfully.'));
    }

    public function destroy(AccountManager $accountManager)
    {
        $accountManager->delete();
        return redirect()
            ->route('admin-panel.account-managers.index')
            ->with('success', __('Account Manager deleted successfully.'));
    }
}