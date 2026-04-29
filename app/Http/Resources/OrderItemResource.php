<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'variation' => $this->whenLoaded('variation', fn () => new ProductVariationResource($this->variation)),
            'design' => $this->whenLoaded('design', fn () => $this->design ? new DesignResource($this->design) : null),
        ];
    }
}
