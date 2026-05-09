<?php

declare(strict_types=1);

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->withoutMiddleware(ThrottleRequests::class);
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Concurrency');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Build an active Product with one active price and one inventory row.
 */
function makeProductWithStock(
    int $stock = 10,
    int $reserved = 0,
    string $price = '100.00',
    string $currency = 'TRY',
    bool $isActive = true,
): Product {
    /** @var Product $product */
    $product = Product::factory()
        ->state(['is_active' => $isActive])
        ->has(
            ProductPrice::factory()->state([
                'amount' => $price,
                'currency' => $currency,
                'is_active' => true,
            ]),
            'prices',
        )
        ->has(
            Inventory::factory()->withStock($stock, $reserved),
            'inventory',
        )
        ->create();

    return $product->load(['activePrice', 'inventory']);
}
