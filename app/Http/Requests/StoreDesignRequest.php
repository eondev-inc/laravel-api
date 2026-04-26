<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StoreDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('designs.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'string', 'exists:products,uuid'],
            'image' => ['required', 'image', 'mimes:png,jpg', 'max:5120'],
        ];
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Forbidden. Required permission: designs.create.');
    }
}
