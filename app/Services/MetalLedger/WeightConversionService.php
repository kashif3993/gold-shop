<?php

declare(strict_types=1);

namespace App\Services\MetalLedger;

use App\Models\Setting;
use InvalidArgumentException;

/**
 * Converts between the shop's weight units — gram, tola, masha, ratti, point
 * — losslessly in both directions.
 *
 * The unit ladder is fixed: 1 tola = 12 masha = 96 ratti = 9600 point. The
 * one figure the shop actually configures is grams-per-tola; every other
 * factor is derived from it. Per Task 1 of the Metal Ledger brief: "The gram
 * value of a tola is one configured number, set once — settle it with the
 * owner, and from then on it is never retyped anywhere." It lives in
 * Settings (key `tola_grams`), not as a constant, so it is genuinely set
 * once rather than hardcoded in more than one place.
 */
final class WeightConversionService
{
    private const UNITS = ['gram', 'tola', 'masha', 'ratti', 'point'];

    private const POINTS_PER_RATTI = 100;

    private const POINTS_PER_MASHA = self::POINTS_PER_RATTI * 8; // 800

    private const POINTS_PER_TOLA = self::POINTS_PER_MASHA * 12; // 9,600

    /** Used only if the shop has never set `tola_grams` in Settings. */
    private const FALLBACK_TOLA_GRAMS = 11.6638038;

    public function tolaGrams(): float
    {
        return (float) Setting::get('tola_grams', self::FALLBACK_TOLA_GRAMS);
    }

    /** Grams-per-unit for each supported unit, derived from {@see tolaGrams()}. */
    private function factors(): array
    {
        $tola = $this->tolaGrams();

        return [
            'gram' => 1.0,
            'tola' => $tola,
            'masha' => $tola / 12,
            'ratti' => $tola / 12 / 8,
            'point' => $tola / self::POINTS_PER_TOLA,
        ];
    }

    private function factorFor(string $unit): float
    {
        $factor = $this->factors()[$unit] ?? null;

        if ($factor === null) {
            throw new InvalidArgumentException(
                "Unknown weight unit [{$unit}]. Expected one of: ".implode(', ', self::UNITS)
            );
        }

        return $factor;
    }

    public function toGrams(float $value, string $unit): float
    {
        return $value * $this->factorFor($unit);
    }

    public function fromGrams(float $grams, string $unit): float
    {
        return $grams / $this->factorFor($unit);
    }

    /**
     * Break a gram value into its carried tola/masha/ratti/point parts, i.e.
     * masha < 12, ratti < 8, point < 100 — the same breakdown a cashier
     * reads a weight back as.
     *
     * @return array{tola:int,masha:int,ratti:int,point:float}
     */
    public function gramsToTraditional(float $grams): array
    {
        if ($grams <= 0) {
            return ['tola' => 0, 'masha' => 0, 'ratti' => 0, 'point' => 0.0];
        }

        // Work in points throughout — it's the finest unit, so converting
        // once here (rather than per-part) is the only rounding that happens
        // before the carry below.
        $points = round($this->fromGrams($grams, 'point'), 2);

        $tola = (int) floor($points / self::POINTS_PER_TOLA);
        $points -= $tola * self::POINTS_PER_TOLA;

        $masha = (int) floor($points / self::POINTS_PER_MASHA);
        $points -= $masha * self::POINTS_PER_MASHA;

        $ratti = (int) floor($points / self::POINTS_PER_RATTI);
        $points -= $ratti * self::POINTS_PER_RATTI;

        $point = round($points, 2);

        // Rounding above can push a part up to exactly its own rollover
        // value (e.g. 100.00 point) — carry it rather than display it.
        if ($point >= self::POINTS_PER_RATTI) {
            $point -= self::POINTS_PER_RATTI;
            $ratti++;
        }
        if ($ratti >= 8) {
            $ratti -= 8;
            $masha++;
        }
        if ($masha >= 12) {
            $masha -= 12;
            $tola++;
        }

        return ['tola' => $tola, 'masha' => $masha, 'ratti' => $ratti, 'point' => $point];
    }

    /**
     * Sum a tola/masha/ratti/point breakdown back into grams.
     *
     * Deliberately NOT rounded here — a point is worth roughly 0.0012 g,
     * finer than the shop's 3-decimal storage precision, so rounding
     * eagerly inside a general-purpose conversion would itself be the kind
     * of double-rounding that makes a value drift when converted twice.
     * Round only at the point of storage (see {@see PurityConversionService}
     * and wherever a caller actually persists a weight), never in here.
     *
     * @param  array{tola?:float,masha?:float,ratti?:float,point?:float}  $units
     */
    public function traditionalToGrams(array $units): float
    {
        return $this->toGrams((float) ($units['tola'] ?? 0), 'tola')
            + $this->toGrams((float) ($units['masha'] ?? 0), 'masha')
            + $this->toGrams((float) ($units['ratti'] ?? 0), 'ratti')
            + $this->toGrams((float) ($units['point'] ?? 0), 'point');
    }
}
