<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePOSTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name'  => 'nullable|string|max:255|regex:/^[\pL\s\-]+$/u', // restrict to letters, spaces, hyphens
            'customer_phone' => 'nullable|string|max:50|regex:/^\+?[0-9\s\-]+$/', // restrict to phone characters
            'goldRate'       => 'required|numeric|min:1',
            'discount'       => 'required|numeric|min:0',
            'discount_reason'=> 'nullable|string|max:255',
            'discountType'   => 'required|in:flat,percentage',
            'paymentMethod'  => 'required|in:Cash,Card,Transfer,cash,card,bank_transfer,credit',
            'items'          => 'required_without:exchanges|array',
            'items.*.id'     => 'required|exists:items,id',
            
            'exchanges'                      => 'nullable|array',
            'exchanges.*.metal_type_id'      => 'required_with:exchanges|exists:metal_types,id',
            'exchanges.*.purity_id'          => 'required_with:exchanges|exists:purities,id',
            'exchanges.*.weight_grams'       => 'required_with:exchanges|numeric|min:0.001',
            'exchanges.*.deduction_percent'  => 'required_with:exchanges|numeric|min:0|max:100',
            'exchanges.*.valuation'          => 'required_with:exchanges|numeric|min:0',
        ];
    }
}
