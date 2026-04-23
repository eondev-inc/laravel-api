<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variation_id' => ['required', 'string', 'exists:product_variations,uuid'],
            'design_id' => ['sometimes', 'nullable', 'string', 'exists:designs,uuid'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
