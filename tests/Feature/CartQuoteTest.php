<?php

declare(strict_types=1);

it('returns priced lines and subtotal computed via bcmath', function (): void {
    $a = makeProductWithStock(price: '0.10');
    $b = makeProductWithStock(price: '0.20');

    $this->postJson('/api/cart/quote', [
        'customer_email' => 'u@x.com',
        'items' => [
            ['product_id' => $a->id, 'quantity' => 1],
            ['product_id' => $b->id, 'quantity' => 1],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('subtotal', '0.30')
        ->assertJsonPath('currency', 'TRY')
        ->assertJsonPath('items.0.line_total', '0.10')
        ->assertJsonPath('items.1.line_total', '0.20');
});

it('rejects an inactive product with PRODUCT_INACTIVE', function (): void {
    $product = makeProductWithStock(isActive: false);

    $this->postJson('/api/cart/quote', [
        'customer_email' => 'u@x.com',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'PRODUCT_INACTIVE')
        ->assertJsonPath('error.details.0.product_id', $product->id);
});

it('rejects insufficient stock with INSUFFICIENT_STOCK 409', function (): void {
    $product = makeProductWithStock(stock: 5, reserved: 4);

    $this->postJson('/api/cart/quote', [
        'customer_email' => 'u@x.com',
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
    ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK')
        ->assertJsonPath('error.details.0.product_id', $product->id)
        ->assertJsonPath('error.details.0.requested', 5)
        ->assertJsonPath('error.details.0.available', 1);
});

it('rejects quantity = 0 via validation', function (): void {
    $product = makeProductWithStock();

    $this->postJson('/api/cart/quote', [
        'customer_email' => 'u@x.com',
        'items' => [['product_id' => $product->id, 'quantity' => 0]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects mixed currencies with MIXED_CURRENCY', function (): void {
    $tryProduct = makeProductWithStock(currency: 'TRY');
    $usdProduct = makeProductWithStock(currency: 'USD');

    $this->postJson('/api/cart/quote', [
        'customer_email' => 'u@x.com',
        'items' => [
            ['product_id' => $tryProduct->id, 'quantity' => 1],
            ['product_id' => $usdProduct->id, 'quantity' => 1],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'MIXED_CURRENCY');
});

it('rejects a non-existent product with PRODUCT_NOT_FOUND', function (): void {
    $this->postJson('/api/cart/quote', [
        'customer_email' => 'u@x.com',
        'items' => [['product_id' => 999_999, 'quantity' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND')
        ->assertJsonPath('error.details.0.product_id', 999_999);
});

it('ignores client-supplied unit_price and uses the database price', function (): void {
    $product = makeProductWithStock(price: '129.90');

    $this->postJson('/api/cart/quote', [
        'customer_email' => 'u@x.com',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '0.01']],
    ])
        ->assertOk()
        ->assertJsonPath('items.0.unit_price', '129.90')
        ->assertJsonPath('subtotal', '129.90');
});
