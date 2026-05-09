<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ReservationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReservationItem
 */
class ReservationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'name' => $this->whenLoaded('product', fn (): ?string => $this->product?->name),
            'sku' => $this->whenLoaded('product', fn (): ?string => $this->product?->sku),
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'line_total' => (string) $this->line_total,
        ];
    }
}
