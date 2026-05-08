<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
#[UseModel(StockMovement::class)]
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'reservation_id' => null,
            'type' => 'reserved',
            'quantity_delta' => -1,
            'quantity_after' => 99,
            'reserved_after' => 1,
            'reason' => null,
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
