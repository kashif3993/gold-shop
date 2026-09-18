<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Same cart shape as a normal sale (StorePOSTransactionRequest) — the
 * payment method is implicitly "bank QR" here, so there's no field for it.
 */
class StartBankQrPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'customer_name' => 'nullable|string|max:255|regex:/^[\pL\s\-]+$/u',
            'customer_phone' => 'nullable|string|max:50|regex:/^\+?[0-9\s\-]+$/',
            'discount' => 'required|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
            'discountType' => 'required|in:flat,percentage',
            'items' => 'required_without:exchanges|array',
            'items.*.id' => 'required|exists:items,id',
            'items.*.rate_override' => 'nullable|numeric|min:0.01',

            // Pricing is resolved server-side per purity (see POSSaleService) —
            // nothing priced here is trusted from the client, other than an
            // explicit per-line rate_override, which is always audited.
            'exchanges' => 'nullable|array',
            'exchanges.*.metal_type_id' => 'required_with:exchanges|exists:metal_types,id',
            'exchanges.*.purity_id' => 'required_with:exchanges|exists:purities,id',
            'exchanges.*.weight_grams' => 'required_with:exchanges|numeric|min:0.001',
            'exchanges.*.deduction_percent' => 'required_with:exchanges|numeric|min:0|max:100',
        ];
    }
}
