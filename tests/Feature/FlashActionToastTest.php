<?php

namespace Tests\Feature;

use App\Http\Middleware\FlashActionToast;
use Tests\TestCase;

/**
 * Memastikan aksi mutasi tanpa pesan khusus tetap mendapat pesan sukses
 * yang sesuai jenis aksinya (AGENTS.md §7.3).
 */
class FlashActionToastTest extends TestCase
{
    public function test_message_follows_the_action_verb(): void
    {
        $cases = [
            'customers.store' => 'Saved successfully.',
            'leads.update' => 'Changes saved successfully.',
            'customers.destroy' => 'Deleted successfully.',
            'sales.price-requests.accept' => 'Confirmed successfully.',
            'sales.price-requests.cancel' => 'Cancelled successfully.',
            'sales.price-requests.reject' => 'Rejected successfully.',
            'sales.proposals.submit' => 'Sent successfully.',
            'admin.invoices.upload' => 'Uploaded successfully.',
            'price-requests.assign' => 'Assigned successfully.',
            'trash.restore' => 'Restored successfully.',
            'documents.archive' => 'Archived successfully.',
        ];

        foreach ($cases as $route => $expected) {
            $this->assertSame($expected, FlashActionToast::messageFor($route), "Route: {$route}");
        }
    }

    public function test_unknown_action_uses_generic_message(): void
    {
        $this->assertSame('Action completed successfully.', FlashActionToast::messageFor('leads.reorder'));
        $this->assertSame('Action completed successfully.', FlashActionToast::messageFor(null));
    }
}
