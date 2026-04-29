<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->uuid,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
