<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'file_url' => $this->file_url,
            'file_extension' => $this->file_extension,
            'is_active' => $this->is_active,
            'product_id' => $this->whenLoaded('product', fn () => $this->product->uuid),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
