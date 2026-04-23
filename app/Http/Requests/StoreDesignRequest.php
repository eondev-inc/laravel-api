<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'string', 'exists:products,uuid'],
            'image' => ['required', 'image', 'mimes:png,jpg', 'max:5120'],
        ];
    }
}
