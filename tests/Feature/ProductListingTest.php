<?php

declare(strict_types=1);

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\DB;

it('paginates with default per_page=20', function (): void {
    Product::factory()
        ->count(25)
        ->has(ProductPrice::factory(), 'prices')
        ->has(Inventory::factory(), 'inventory')
        ->create();

    $response = $this->getJson('/api/products')->assertOk();

    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.total'))->toBe(25);
    expect($response->json('meta.per_page'))->toBe(20);
});

it('caps per_page at 100', function (): void {
    Product::factory()
        ->count(3)
        ->has(ProductPrice::factory(), 'prices')
        ->has(Inventory::factory(), 'inventory')
        ->create();

    $response = $this->getJson('/api/products?per_page=500')->assertOk();

    expect($response->json('meta.per_page'))->toBe(100);
});

it('excludes inactive products by default', function (): void {
    Product::factory()
        ->count(3)
        ->has(ProductPrice::factory(), 'prices')
        ->has(Inventory::factory(), 'inventory')
        ->create();
    Product::factory()
        ->count(2)
        ->inactive()
        ->has(ProductPrice::factory(), 'prices')
        ->has(Inventory::factory(), 'inventory')
        ->create();

    $response = $this->getJson('/api/products')->assertOk();

    expect($response->json('meta.total'))->toBe(3);
    expect(collect($response->json('data'))->pluck('is_active')->unique()->all())->toBe([true]);
});

it('searches by name with case-insensitive ilike', function (): void {
    makeProductWithStock();
    Product::factory()->state(['name' => 'Special Edition Mug', 'sku' => 'MUG-001'])
        ->has(ProductPrice::factory(), 'prices')
        ->has(Inventory::factory(), 'inventory')
        ->create();

    $response = $this->getJson('/api/products?search=SPECIAL')->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.name'))->toBe('Special Edition Mug');
});

it('searches by sku', function (): void {
    makeProductWithStock();
    Product::factory()->state(['sku' => 'KEYBOARD-007'])
        ->has(ProductPrice::factory(), 'prices')
        ->has(Inventory::factory(), 'inventory')
        ->create();

    $response = $this->getJson('/api/products?search=KEYBOARD')->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.sku'))->toBe('KEYBOARD-007');
});

it('sorts by newest by default', function (): void {
    $old = makeProductWithStock();
    $old->forceFill(['created_at' => now()->subDays(5)])->save();
    $new = makeProductWithStock();

    $response = $this->getJson('/api/products')->assertOk();

    expect($response->json('data.0.id'))->toBe($new->id);
    expect($response->json('data.1.id'))->toBe($old->id);
});

it('sorts by price_asc and price_desc', function (): void {
    $cheap = makeProductWithStock(price: '10.00');
    $mid = makeProductWithStock(price: '50.00');
    $expensive = makeProductWithStock(price: '500.00');

    $asc = $this->getJson('/api/products?sort=price_asc')->assertOk();
    expect(collect($asc->json('data'))->pluck('id')->all())
        ->toBe([$cheap->id, $mid->id, $expensive->id]);

    $desc = $this->getJson('/api/products?sort=price_desc')->assertOk();
    expect(collect($desc->json('data'))->pluck('id')->all())
        ->toBe([$expensive->id, $mid->id, $cheap->id]);
});

it('exposes available_stock as quantity minus reserved_quantity', function (): void {
    makeProductWithStock(stock: 10, reserved: 3);

    $response = $this->getJson('/api/products')->assertOk();

    expect($response->json('data.0.available_stock'))->toBe(7);
});

it('returns price as a structured object with amount and currency', function (): void {
    makeProductWithStock(price: '129.90', currency: 'TRY');

    $response = $this->getJson('/api/products')->assertOk();

    expect($response->json('data.0.price'))->toBe([
        'amount' => '129.90',
        'currency' => 'TRY',
    ]);
});

it('runs at most 4 queries regardless of result count (N+1 free)', function (): void {
    Product::factory()
        ->count(50)
        ->has(ProductPrice::factory(), 'prices')
        ->has(Inventory::factory(), 'inventory')
        ->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson('/api/products?per_page=50')->assertOk();

    $count = count(DB::getQueryLog());
    expect($count)->toBeLessThanOrEqual(4);
});
