<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'file_url' => $this->file_path
                ? Storage::disk('s3_private')->temporaryUrl($this->file_path, now()->addMinutes(15))
                : null,
            'file_extension' => $this->file_extension,
            'is_active' => $this->is_active,
            'product_id' => $this->whenLoaded('product', fn () => $this->product->uuid),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
