<?php

namespace Tests\Unit;

use App\Support\Quantity;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    public function test_format_removes_only_trailing_decimal_zeroes(): void
    {
        foreach (['0.0000' => '0', '30.0000' => '30', '2.5000' => '2,5', '1234.1250' => '1.234,125', '0.0001' => '0,0001', '-10.0000' => '-10'] as $value => $expected) {
            $this->assertSame($expected, Quantity::format($value));
        }
        $this->assertSame('100', Quantity::input('100.0000'));
        $this->assertSame('', Quantity::input(null));
    }
}
