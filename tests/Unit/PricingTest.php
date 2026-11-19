<?php

namespace Tests\Unit;

use App\Support\Pricing;
use PHPUnit\Framework\TestCase;

class PricingTest extends TestCase
{
    public function test_net_cost_removes_vat_when_price_includes_tax(): void
    {
        $this->assertSame(1000000.0, Pricing::netCost(1120000, true, 12));
        $this->assertSame(1000000.0, Pricing::netCost(1000000, false, 12));
    }

    public function test_selling_price_uses_gross_margin_from_selling_price(): void
    {
        // Margin 20% dari harga jual: modal 800.000 -> jual 1.000.000.
        $this->assertSame(1000000.0, Pricing::sellingPrice(800000, 20));
        $this->assertSame(1333334.0, Pricing::sellingPrice(1000000, 25));
    }

    public function test_selling_price_accounts_for_discount(): void
    {
        // Modal 2 x 800.000 + diskon 100.000, margin 20% -> harga satuan 1.062.500.
        $this->assertSame(1050000.0, Pricing::sellingPrice(800000, 20, 2, 100000));
    }

    public function test_margin_percent_is_calculated_before_tax(): void
    {
        $this->assertSame(20.0, Pricing::marginPercent(1000000, 800000));
        $this->assertSame(20.0, Pricing::marginPercent(1050000, 800000, 2, 100000));
    }

    public function test_margin_percent_is_null_when_sales_are_zero(): void
    {
        $this->assertNull(Pricing::marginPercent(0, 500000));
    }
}
