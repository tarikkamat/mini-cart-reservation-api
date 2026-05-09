<?php

declare(strict_types=1);

use App\Domain\Cart\Data\CartItemData;
use App\Domain\Cart\Data\CartQuoteResult;
use App\Domain\Cart\Exceptions\MixedCurrencyException;
use App\Domain\Cart\Exceptions\ProductInactiveException;
use App\Domain\Cart\Exceptions\ProductNotFoundException;
use App\Domain\Cart\Services\CartCalculator;
use App\Domain\Reservation\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Collection;

function makeProduct(
    int $id,
    string $sku = 'PRD-1',
    string $name = 'Foo',
    bool $isActive = true,
    string $price = '100.00',
    string $currency = 'TRY',
    int $stock = 100,
    int $reserved = 0,
): Product {
    $product = new Product(['sku' => $sku, 'name' => $name, 'is_active' => $isActive]);
    $product->id = $id;

    $product->setRelation('activePrice', new ProductPrice([
        'amount' => $price,
        'currency' => $currency,
        'is_active' => true,
    ]));

    $product->setRelation('inventory', new Inventory([
        'quantity' => $stock,
        'reserved_quantity' => $reserved,
    ]));

    return $product;
}

it('returns an empty result for empty items', function (): void {
    $result = (new CartCalculator)->calculate(
        items: new Collection,
        productsKeyedById: new Collection,
    );

    expect($result)->toBeInstanceOf(CartQuoteResult::class);
    expect($result->lines)->toHaveCount(0);
    expect($result->subtotal)->toBe('0.00');
});

it('calculates a single-item line total and subtotal', function (): void {
    $product = makeProduct(id: 1, price: '129.90', currency: 'TRY');

    $result = (new CartCalculator)->calculate(
        items: collect([new CartItemData(productId: 1, quantity: 2)]),
        productsKeyedById: collect([1 => $product]),
    );

    expect($result->lines)->toHaveCount(1);
    expect($result->lines->first()->unitPrice)->toBe('129.90');
    expect($result->lines->first()->lineTotal)->toBe('259.80');
    expect($result->subtotal)->toBe('259.80');
    expect($result->currency)->toBe('TRY');
});

it('accumulates multi-item subtotal using bcmath precision', function (): void {
    $a = makeProduct(id: 1, sku: 'A', price: '0.10', currency: 'TRY');
    $b = makeProduct(id: 2, sku: 'B', price: '0.20', currency: 'TRY');

    $result = (new CartCalculator)->calculate(
        items: collect([
            new CartItemData(productId: 1, quantity: 1),
            new CartItemData(productId: 2, quantity: 1),
        ]),
        productsKeyedById: collect([1 => $a, 2 => $b]),
    );

    expect($result->subtotal)->toBe('0.30');
});

it('throws ProductNotFoundException when productId is missing from products map', function (): void {
    (new CartCalculator)->calculate(
        items: collect([new CartItemData(productId: 99, quantity: 1)]),
        productsKeyedById: new Collection,
    );
})->throws(ProductNotFoundException::class);

it('throws ProductInactiveException for inactive products', function (): void {
    $product = makeProduct(id: 1, isActive: false);

    (new CartCalculator)->calculate(
        items: collect([new CartItemData(productId: 1, quantity: 1)]),
        productsKeyedById: collect([1 => $product]),
    );
})->throws(ProductInactiveException::class);

it('throws InsufficientStockException when requested quantity exceeds available', function (): void {
    $product = makeProduct(id: 1, stock: 5, reserved: 4);

    expect(fn () => (new CartCalculator)->calculate(
        items: collect([new CartItemData(productId: 1, quantity: 2)]),
        productsKeyedById: collect([1 => $product]),
    ))
        ->toThrow(InsufficientStockException::class);
});

it('throws MixedCurrencyException when products span multiple currencies', function (): void {
    $tryProduct = makeProduct(id: 1, currency: 'TRY');
    $usdProduct = makeProduct(id: 2, currency: 'USD');

    (new CartCalculator)->calculate(
        items: collect([
            new CartItemData(productId: 1, quantity: 1),
            new CartItemData(productId: 2, quantity: 1),
        ]),
        productsKeyedById: collect([1 => $tryProduct, 2 => $usdProduct]),
    );
})->throws(MixedCurrencyException::class);
