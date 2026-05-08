<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reservation\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
#[UseModel(Reservation::class)]
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'customer_email' => fake()->safeEmail(),
            'status' => ReservationStatus::Active,
            'subtotal' => fake()->randomFloat(2, 10, 1000),
            'currency' => 'TRY',
            'expires_at' => now()->addMinutes((int) config('reservation.ttl_minutes', 15)),
            'released_at' => null,
            'idempotency_key' => null,
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Released,
            'released_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Expired,
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    public function committed(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::Committed]);
    }

    public function expiring(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subSecond()]);
    }
}
