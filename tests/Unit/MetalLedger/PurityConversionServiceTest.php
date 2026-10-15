<?php

namespace Tests\Unit\MetalLedger;

use App\Services\MetalLedger\PurityConversionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PurityConversionServiceTest extends TestCase
{
    private PurityConversionService $purity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purity = new PurityConversionService;
    }

    /**
     * The brief's own worked example for silver is exact arithmetic (925 =
     * 92.5% fine). Its 22K gold example reads "9.167 g of pure gold" from a
     * 10 g chain, but 22/24 karat is a repeating decimal (91.666...%) — the
     * brief itself lists this as an open question ("purity factors — the
     * shop's trade practice may not use the textbook ratio"), not a
     * developer's call. Asked the brief owner directly: confirmed 91.66%
     * (rounding down, not up) is the figure to use, giving 9.166 g — not
     * the brief text's own 9.167 g. That confirmed answer is the one this
     * test proves, since it's the one actually settled with the owner.
     */
    public function test_matches_the_owners_confirmed_22k_fineness(): void
    {
        $this->assertSame(9.166, $this->purity->pureWeight(10.0, 91.66));

        // 925 (sterling) silver is exact: 92.5% fine — matches the brief as written.
        $this->assertSame(9.25, $this->purity->pureWeight(10.0, 92.5));
    }

    public function test_24k_and_999_fine_metal_are_effectively_the_full_weight(): void
    {
        $this->assertSame(9.99, $this->purity->pureWeight(10.0, 99.9));
    }

    public function test_zero_fineness_and_zero_weight_both_yield_zero(): void
    {
        $this->assertSame(0.0, $this->purity->pureWeight(10.0, 0.0));
        $this->assertSame(0.0, $this->purity->pureWeight(0.0, 91.66));
    }

    public function test_rounds_to_three_decimal_places_once(): void
    {
        // 7.111 * 60.75 / 100 = 4.3199325 -> rounds to 4.320.
        $this->assertSame(4.32, $this->purity->pureWeight(7.111, 60.75));
    }

    public function test_rejects_a_negative_weight(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->purity->pureWeight(-1.0, 91.66);
    }

    public function test_rejects_a_fineness_outside_zero_to_a_hundred(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->purity->pureWeight(10.0, 101.0);
    }
}
