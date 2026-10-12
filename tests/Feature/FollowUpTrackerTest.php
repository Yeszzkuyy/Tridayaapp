<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_followup_with_soft_deleted_customer_still_resolves(): void
    {
        $customer = Customer::create(['name' => 'PT Bina']);
        $followUp = FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'Follow up lanjutan',
            'follow_up_date' => now(),
        ]);

        $customer->delete();

        $this->assertSame('PT Bina', $followUp->fresh()->customer->name);
    }
}
