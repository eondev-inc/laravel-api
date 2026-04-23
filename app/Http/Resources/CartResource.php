<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'total' => $this->whenLoaded('items', fn () => $this->items->sum(fn ($item) => $item->quantity * $item->unit_price)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
