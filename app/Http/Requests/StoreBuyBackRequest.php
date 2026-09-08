<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuyBackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Normalise the payment method so casing/synonyms from the POS UI
     * ("Cash", "Bank_Transfer", ...) resolve consistently. The controller
     * maps the lowercased value onto the DB enum.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('payment_method') && is_string($this->input('payment_method'))) {
            $this->merge([
                'payment_method' => strtolower($this->input('payment_method')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_name'                 => 'nullable|string|max:255',
            'customer_phone'                => 'nullable|string|max:50',
            'party_id'                      => 'nullable|exists:parties,id',
            'item_id'                       => 'nullable|exists:items,id',
            'original_sale_transaction_id'  => 'nullable|exists:transactions,id',
            'metal_type_id'                 => 'required|exists:metal_types,id',
            'purity_id'                     => 'required|exists:purities,id',
            'weight_grams'                  => 'required|numeric|gt:0',
            'rate_per_gram'                 => 'required|numeric|gt:0',
            'deduction_percent'             => 'nullable|numeric|min:0|max:100',
            'payment_method'                => 'required|in:cash,card,transfer,bank_transfer,credit',
            'notes'                         => 'nullable|string|max:1000',
        ];
    }
}
