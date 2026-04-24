<?php

namespace Tests\Unit;

use App\Support\PaymentMethodBuckets;
use PHPUnit\Framework\TestCase;

class PaymentMethodBucketsTest extends TestCase
{
    public function test_cash_variants_are_grouped_under_cash(): void
    {
        $this->assertSame('cash', PaymentMethodBuckets::normalize('Cash'));
        $this->assertSame('cash', PaymentMethodBuckets::normalize('bulky cash'));
        $this->assertSame('cash', PaymentMethodBuckets::normalize('petty_cash'));
        $this->assertSame('cash', PaymentMethodBuckets::normalize('petty-cash'));
    }

    public function test_blank_and_unknown_methods_fall_under_other(): void
    {
        $this->assertSame('other', PaymentMethodBuckets::normalize(null));
        $this->assertSame('other', PaymentMethodBuckets::normalize(''));
        $this->assertSame('other', PaymentMethodBuckets::normalize('Cheque'));
        $this->assertSame('other', PaymentMethodBuckets::normalize('Direct'));
    }
}
