<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'price' => $this->whenLoaded('activePrice', fn (): ?array => $this->activePrice ? [
                'amount' => (string) $this->activePrice->amount,
                'currency' => $this->activePrice->currency,
            ] : null),
            'available_stock' => $this->whenLoaded('inventory', fn (): int => $this->inventory?->available ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
