<?php

namespace Tests\Feature\MetalLedger;

use App\Models\Setting;
use App\Services\MetalLedger\PurityConversionService;
use App\Services\MetalLedger\WeightConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private WeightConversionService $weight;

    protected function setUp(): void
    {
        parent::setUp();
        $this->weight = new WeightConversionService;
    }

    public function test_tola_grams_falls_back_when_not_configured(): void
    {
        $this->assertEqualsWithDelta(11.6638038, $this->weight->tolaGrams(), 0.0000001);
    }

    public function test_tola_grams_reads_the_owners_configured_value(): void
    {
        Setting::put('tola_grams', '11.664');

        $this->assertEqualsWithDelta(11.664, $this->weight->tolaGrams(), 0.0000001);
    }

    /**
     * The brief's own "Done when" line: a 1 tola item reads back identically
     * in grams, masha and ratti.
     */
    public function test_one_tola_reads_back_identically_in_every_unit(): void
    {
        Setting::put('tola_grams', '11.6638038');

        $grams = $this->weight->toGrams(1, 'tola');
        $masha = $this->weight->fromGrams($grams, 'masha');
        $ratti = $this->weight->fromGrams($grams, 'ratti');

        $this->assertEqualsWithDelta(11.664, $grams, 0.001);
        $this->assertEqualsWithDelta(12.0, $masha, 0.001);
        $this->assertEqualsWithDelta(96.0, $ratti, 0.001);

        // And back the other way — masha and ratti both return to 1 tola.
        $this->assertEqualsWithDelta(1.0, $this->weight->fromGrams($this->weight->toGrams($masha, 'masha'), 'tola'), 0.0001);
        $this->assertEqualsWithDelta(1.0, $this->weight->fromGrams($this->weight->toGrams($ratti, 'ratti'), 'tola'), 0.0001);
    }

    public function test_gram_to_traditional_round_trips_for_a_whole_tola(): void
    {
        $grams = $this->weight->toGrams(1, 'tola');

        $this->assertEquals(['tola' => 1, 'masha' => 0, 'ratti' => 0, 'point' => 0.0], $this->weight->gramsToTraditional($grams));
        $this->assertEqualsWithDelta($grams, $this->weight->traditionalToGrams(['tola' => 1]), 0.001);
    }

    public function test_gram_to_traditional_round_trips_for_a_mixed_weight(): void
    {
        // 2 tola, 5 masha, 3 ratti, 40 point — a value that exercises every unit.
        $original = ['tola' => 2, 'masha' => 5, 'ratti' => 3, 'point' => 40];
        $grams = $this->weight->traditionalToGrams($original);

        $this->assertEquals($original, $this->weight->gramsToTraditional($grams));
    }

    public function test_carry_rolls_a_full_unit_up_to_the_next(): void
    {
        // 12 masha carries to 1 tola; 8 ratti carries to 1 masha; 100 point carries to 1 ratti.
        $grams = $this->weight->traditionalToGrams(['masha' => 12]);
        $this->assertEquals(['tola' => 1, 'masha' => 0, 'ratti' => 0, 'point' => 0.0], $this->weight->gramsToTraditional($grams));

        $grams = $this->weight->traditionalToGrams(['ratti' => 8]);
        $this->assertEquals(['tola' => 0, 'masha' => 1, 'ratti' => 0, 'point' => 0.0], $this->weight->gramsToTraditional($grams));
    }

    public function test_converting_back_and_forth_twice_does_not_drift(): void
    {
        $grams = 37.428;

        $roundTripped = $this->weight->traditionalToGrams(
            $this->weight->gramsToTraditional(
                $this->weight->traditionalToGrams($this->weight->gramsToTraditional($grams))
            )
        );

        $this->assertEqualsWithDelta($grams, $roundTripped, 0.001);
    }

    public function test_zero_and_negative_inputs_do_not_break_the_breakdown(): void
    {
        $this->assertEquals(['tola' => 0, 'masha' => 0, 'ratti' => 0, 'point' => 0.0], $this->weight->gramsToTraditional(0));
        $this->assertEquals(['tola' => 0, 'masha' => 0, 'ratti' => 0, 'point' => 0.0], $this->weight->gramsToTraditional(-5));
    }

    public function test_an_unknown_unit_is_rejected_rather_than_silently_misconverted(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->weight->toGrams(1, 'kilogram');
    }

    /**
     * The brief's second "Done when" clause: a page of mixed-unit,
     * mixed-purity items totals to the same figure whether the weights
     * were keyed in grams or in tola.
     */
    public function test_mixed_unit_mixed_purity_page_totals_match_regardless_of_input_unit(): void
    {
        Setting::put('tola_grams', '11.6638038');
        $purity = new PurityConversionService;

        // Three items, different metal/purity each — weights chosen clean in grams.
        $items = [
            ['grams' => 10.0, 'fineness' => 91.67], // 22K gold
            ['grams' => 5.0, 'fineness' => 99.9],   // 24K gold
            ['grams' => 20.0, 'fineness' => 92.5],  // 925 silver
        ];

        // Page A: every weight keyed in grams directly.
        $totalFromGrams = array_reduce(
            $items,
            fn (float $sum, array $item) => $sum + $purity->pureWeight($item['grams'], $item['fineness']),
            0.0
        );

        // Page B: the exact same items, but keyed in tola on the entry
        // screen and converted back to grams before pricing.
        $totalFromTola = array_reduce(
            $items,
            function (float $sum, array $item) use ($purity) {
                $tola = $this->weight->fromGrams($item['grams'], 'tola');
                $gramsFromTola = $this->weight->toGrams($tola, 'tola');

                return $sum + $purity->pureWeight($gramsFromTola, $item['fineness']);
            },
            0.0
        );

        $this->assertEqualsWithDelta($totalFromGrams, $totalFromTola, 0.001);
    }
}
