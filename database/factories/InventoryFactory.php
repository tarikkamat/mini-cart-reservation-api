<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
#[UseModel(Inventory::class)]
class InventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(0, 100),
            'reserved_quantity' => 0,
            'version' => 0,
        ];
    }

    public function withStock(int $quantity, int $reserved = 0): static
    {
        return $this->state(fn () => [
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
        ]);
    }

    public function empty(): static
    {
        return $this->state(fn () => [
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);
    }
}
