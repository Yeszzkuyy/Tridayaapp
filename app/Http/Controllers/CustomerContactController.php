<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Services\CustomerContactService;
use Illuminate\Http\Request;

class CustomerContactController extends Controller
{
    public function __construct(private CustomerContactService $contacts) {}

    /**
     * Show the form for creating a new contact.
     */
    public function create(Customer $customer)
    {
        $this->authorize('update', $customer);

        return view('customer_contacts.create', compact('customer'));
    }

    /**
     * Store a newly created contact.
     */
    public function store(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $validated = $this->validateContact($request);
        $validated['is_primary'] = $request->boolean('is_primary');

        $this->contacts->create($customer, $validated);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('PIC added successfully.'));
    }

    /**
     * Show the form for editing a contact.
     */
    public function edit(CustomerContact $customerContact)
    {
        $this->authorize('update', $customerContact->customer);

        $customer = $customerContact->customer;

        return view('customer_contacts.edit', compact('customerContact', 'customer'));
    }

    /**
     * Update the specified contact.
     */
    public function update(Request $request, CustomerContact $customerContact)
    {
        $this->authorize('update', $customerContact->customer);

        $validated = $this->validateContact($request);
        $validated['is_primary'] = $request->boolean('is_primary');

        $this->contacts->update($customerContact, $validated);

        return redirect()
            ->route('customers.show', $customerContact->customer)
            ->with('success', __('PIC updated successfully.'));
    }

    /**
     * Toggle primary PIC dari daftar contacts.
     */
    public function togglePrimary(CustomerContact $customerContact)
    {
        $this->authorize('update', $customerContact->customer);

        if (! $customerContact->is_primary) {
            $this->contacts->setPrimary($customerContact);
        } else {
            $this->contacts->update($customerContact, ['is_primary' => false]);
        }

        return redirect()->route('customers.show', $customerContact->customer);
    }

    /**
     * Remove the specified contact.
     */
    public function destroy(CustomerContact $customerContact)
    {
        $this->authorize('update', $customerContact->customer);

        $customer = $customerContact->customer;
        $this->contacts->delete($customerContact);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('PIC deleted successfully.'));
    }

    /** @return array<string,mixed> */
    private function validateContact(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'whatsapp' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);
    }
}
