<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'party_type_id' => ['required', 'integer', 'exists:party_types,id'],
            'name'          => ['required', 'string', 'max:150'],
            'phone'         => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-]+$/'],
            'address'       => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone may only contain digits, spaces, hyphens and an optional leading +.',
        ];
    }
}
