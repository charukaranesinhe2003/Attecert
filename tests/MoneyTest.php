<?php

declare(strict_types=1);

namespace Tests;

use App\Helpers\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testMoneyUsesDecimalArithmetic(): void
    {
        $this->assertSame('14.00', Money::add('3.50', Money::add('2.50', '8.00')));
        $this->assertSame('10.50', Money::mul('3', '3.50'));
        $this->assertSame('1.75', Money::percent('35.00', '5'));
        $this->assertSame(1, Money::cmp('10.00', '9.99'));
    }
}
