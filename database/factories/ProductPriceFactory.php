<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
#[UseModel(ProductPrice::class)]
class ProductPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'currency' => 'TRY',
            'amount' => fake()->randomFloat(2, 10, 1000),
            'is_active' => true,
            'valid_from' => now(),
            'valid_to' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function currency(string $currency): static
    {
        return $this->state(fn () => ['currency' => $currency]);
    }
}
