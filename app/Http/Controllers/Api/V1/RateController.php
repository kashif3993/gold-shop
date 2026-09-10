<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DailyRate;
use App\Services\GoldRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class RateController extends Controller
{
    private const TOLA_GRAMS = 11.6638038;

    /** Current effective rates for the ticker — per gram and per tola. */
    public function current(GoldRateService $rates): JsonResponse
    {
        $list = $rates->currentRates()
            ->map(fn ($r) => [
                'metal' => $r->metalType->name ?? '',
                'purity' => $r->purity->name ?? '',
                'per_gram' => (float) $r->rate_per_gram,
                'per_tola' => round((float) $r->rate_per_gram * self::TOLA_GRAMS, 2),
                'source' => $r->source,
                'is_stale' => (bool) $r->is_stale,
            ])
            ->sortBy([['metal', 'asc'], ['purity', 'asc']])
            ->values();

        $newest = DailyRate::where('is_current', true)->max('fetched_at');

        return response()->json([
            'rates' => $list,
            'feed_stale' => $rates->isFeedStale(),
            'as_of' => $newest ? Carbon::parse($newest)->toIso8601String() : null,
        ]);
    }
}
