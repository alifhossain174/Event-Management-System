<?php

namespace Tests\Unit\Finance;

use App\Support\DecimalMath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DecimalMathTest extends TestCase
{
    public function test_it_adds_and_subtracts_without_floating_point(): void
    {
        $this->assertSame('1000000000000000.0000', DecimalMath::add('999999999999999.9999', '0.0001'));
        $this->assertSame('60.1000', DecimalMath::subtract('100.1001', '40.0001'));
        $this->assertSame('-0.0001', DecimalMath::subtract('1.0000', '1.0001'));
    }

    public function test_it_rejects_more_than_four_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecimalMath::normalize('1.00001');
    }

    public function test_it_multiplies_and_rounds_percentages_half_up_without_float_math(): void
    {
        $this->assertSame('59.9850', DecimalMath::multiply('3.0000', '19.9950'));
        $this->assertSame('4.4989', DecimalMath::percentage('59.9850', '7.500000'));
        $this->assertSame('0.0001', DecimalMath::percentage('0.0010', '5.000000'));
        $this->assertSame('-12.3400', DecimalMath::negate('12.3400'));
    }
}
