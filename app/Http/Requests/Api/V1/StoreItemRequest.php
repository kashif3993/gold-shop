<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Purity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:30', 'unique:items,item_code'],
            'qr_payload' => ['nullable', 'string', 'max:255'],
            'item_type' => ['required', 'string', 'max:60'],
            'metal_type_id' => ['required', 'integer', 'exists:metal_types,id'],
            'purity_id' => ['required', 'integer', 'exists:purities,id'],
            'gross_weight_grams' => ['required', 'numeric', 'min:0.001'],
            'stone_weight_grams' => ['nullable', 'numeric', 'min:0'],
            'cutting_loss_grams' => ['nullable', 'numeric', 'min:0'],
            'labour_cost' => ['nullable', 'numeric', 'min:0'],
            'polish_cost' => ['nullable', 'numeric', 'min:0'],
            'purchase_rate_per_gram' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'source_party_id' => ['required', 'integer', 'exists:parties,id'],
            'date_received' => ['required', 'date'],
        ];
    }

    /**
     * Business-rule checks that span multiple fields (can't be expressed as simple rules).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();

            if (! empty($data['metal_type_id']) && ! empty($data['purity_id'])) {
                $purity = Purity::find($data['purity_id']);
                if ($purity && (int) $purity->metal_type_id !== (int) $data['metal_type_id']) {
                    $validator->errors()->add('purity_id', 'The selected purity does not belong to the selected metal type.');
                }

                if ($purity && strtolower((string) $data['item_type']) === 'ring') {
                    $purityName = strtolower(trim((string) $purity->name));
                    $isTwentyFourKarats = preg_match('/^24\s*k$/i', $purityName) === 1;

                    if ($isTwentyFourKarats) {
                        $validator->errors()->add('purity_id', 'Ring items cannot use 24K purity.');
                    }
                }
            }

            $gross = (float) ($data['gross_weight_grams'] ?? 0);
            $stone = (float) ($data['stone_weight_grams'] ?? 0);
            $cutting = (float) ($data['cutting_loss_grams'] ?? 0);

            if (($stone + $cutting) > $gross) {
                $validator->errors()->add('gross_weight_grams', 'Stone weight plus cutting loss cannot exceed the gross weight.');
            }
        });
    }
}
