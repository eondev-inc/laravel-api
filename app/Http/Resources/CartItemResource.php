<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'product_variation_id' => $this->whenLoaded('variation', fn () => $this->variation->uuid),
            'variation' => $this->whenLoaded('variation', fn () => new ProductVariationResource($this->variation)),
            'design_id' => $this->whenLoaded('design', fn () => $this->design?->uuid),
            'design' => $this->whenLoaded('design', fn () => $this->design ? new DesignResource($this->design) : null),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->quantity * $this->unit_price,
        ];
    }
}
