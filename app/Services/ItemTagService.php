<?php

namespace App\Services;

use App\Models\Item;

class ItemTagService
{
    /** Map item type → 3-letter abbreviation */
    private static array $typeAbbrev = [
        'ring'      => 'RNG',
        'bangle'    => 'BNG',
        'necklace'  => 'NCK',
        'earring'   => 'ERN',
        'bracelet'  => 'BRC',
        'chain'     => 'CHN',
        'pendant'   => 'PND',
        'locket'    => 'LCK',
        'nose_pin'  => 'NSP',
        'tops'      => 'TPS',
        'biscuit'   => 'BSC',
        'nugget'    => 'NGT',
        'bar'       => 'BAR',
        'coin'      => 'CON',
        'piece'     => 'PCE',
        'other'     => 'OTH',
    ];

    /** Map metal name (lowercase) → 3-letter abbreviation */
    private static array $metalAbbrev = [
        'gold'     => 'GLD',
        'silver'   => 'SLV',
        'platinum' => 'PLT',
        'diamond'  => 'DMD',
    ];

    /**
     * Generate a structured, readable item code.
     * Format: GLD-RNG-260826-0001
     */
    public static function generateItemCode(string $metalName, string $itemType): string
    {
        $metalKey  = strtolower(trim($metalName));
        $metalAbbr = self::$metalAbbrev[$metalKey]
            ?? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $metalName), 0, 3));

        $typeKey  = strtolower(trim($itemType));
        $typeAbbr = self::$typeAbbrev[$typeKey]
            ?? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $itemType), 0, 3));

        $datePart = now()->format('ymd'); // e.g. 260826

        $prefix = "{$metalAbbr}-{$typeAbbr}-{$datePart}";

        // Count items created today with this same prefix to derive sequence
        $count = Item::where('item_code', 'like', "{$prefix}-%")->count();
        $seq   = str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$seq}";
    }

    /**
     * Generate the JSON string stored in qr_payload.
     * This is what gets encoded into the QR code on the tag.
     */
    public static function generateQrPayload(Item $item): string
    {
        $item->loadMissing(['metalType', 'purity']);

        return json_encode([
            'id'     => $item->id,
            'code'   => $item->item_code,
            'type'   => $item->item_type,
            'metal'  => $item->metalType?->name ?? '',
            'purity' => $item->purity?->name ?? '',
            'net_wt' => (float) $item->net_weight_grams,
            'date'   => $item->date_received?->format('Y-m-d') ?? '',
        ], JSON_UNESCAPED_UNICODE);
    }
}
