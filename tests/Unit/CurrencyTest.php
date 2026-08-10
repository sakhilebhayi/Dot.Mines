<?php

namespace Tests\Unit;

use App\Support\Currency;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function test_symbol_maps_known_codes(): void
    {
        $this->assertSame('R', Currency::symbol('ZAR'));
        $this->assertSame('$', Currency::symbol('usd'));
        $this->assertSame('€', Currency::symbol('EUR'));
        $this->assertSame('R$', Currency::symbol('BRL'));
    }

    public function test_symbol_falls_back_to_the_code_itself_for_unknown_currencies(): void
    {
        $this->assertSame('XYZ ', Currency::symbol('xyz'));
    }

    public function test_symbol_falls_back_to_rand_for_a_missing_currency(): void
    {
        $this->assertSame('R', Currency::symbol(null));
        $this->assertSame('R', Currency::symbol(''));
    }

    public function test_format_combines_symbol_and_number_format(): void
    {
        $this->assertSame('R1,500.00', Currency::format(1500, 'ZAR'));
        $this->assertSame('$1,234.50', Currency::format('1234.5', 'USD'));
    }
}
