<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationItem>
 */
#[UseModel(ReservationItem::class)]
class ReservationItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->randomFloat(2, 10, 500);

        return [
            'reservation_id' => Reservation::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => bcmul((string) $unitPrice, (string) $quantity, 2),
        ];
    }
}
