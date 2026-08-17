<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_code' => $this->item_code,
            'qr_payload' => $this->qr_payload,
            'item_type' => $this->item_type,
            'metal_type_id' => $this->metal_type_id,
            'purity_id' => $this->purity_id,
            'gross_weight_grams' => $this->gross_weight_grams,
            'stone_weight_grams' => $this->stone_weight_grams,
            'cutting_loss_grams' => $this->cutting_loss_grams,
            'net_weight_grams' => $this->net_weight_grams,
            'labour_cost' => $this->labour_cost,
            'polish_cost' => $this->polish_cost,
            'purchase_rate_per_gram' => $this->purchase_rate_per_gram,
            'purchase_price' => $this->purchase_price,
            'source_party_id' => $this->source_party_id,
            'date_received' => $this->date_received?->toDateString(),
            'status' => $this->status,
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at,
        ];
    }
}
