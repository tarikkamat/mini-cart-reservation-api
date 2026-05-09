<?php

declare(strict_types=1);

namespace App\Domain\Cart\Services;

use App\Domain\Cart\Data\CalculatedLine;
use App\Domain\Cart\Data\CartItemData;
use App\Domain\Cart\Data\CartQuoteResult;
use App\Domain\Cart\Exceptions\MixedCurrencyException;
use App\Domain\Cart\Exceptions\ProductInactiveException;
use App\Domain\Cart\Exceptions\ProductNotFoundException;
use App\Domain\Reservation\Exceptions\InsufficientStockException;
use App\Models\Product;
use Illuminate\Support\Collection;

final class CartCalculator
{
    /**
     * Calculate priced cart lines and subtotal from server-side product state.
     *
     * @param  Collection<int, CartItemData>  $items
     * @param  Collection<int, Product>  $productsKeyedById  must be eager-loaded with activePrice + inventory
     */
    public function calculate(Collection $items, Collection $productsKeyedById): CartQuoteResult
    {
        $lines = $items->map(function (CartItemData $item) use ($productsKeyedById): CalculatedLine {
            $product = $productsKeyedById->get($item->productId)
                ?? throw new ProductNotFoundException($item->productId);

            if (! $product->is_active) {
                throw new ProductInactiveException($product->id);
            }

            $available = $product->inventory?->available ?? 0;
            if ($item->quantity > $available) {
                throw new InsufficientStockException($product->id, $item->quantity, $available);
            }

            $unitPrice = (string) $product->activePrice->amount;

            return new CalculatedLine(
                productId: $product->id,
                name: $product->name,
                sku: $product->sku,
                quantity: $item->quantity,
                unitPrice: $unitPrice,
                lineTotal: bcmul($unitPrice, (string) $item->quantity, 2),
            );
        });

        $currencies = $productsKeyedById
            ->filter(fn (Product $p): bool => $items->contains(fn (CartItemData $i): bool => $i->productId === $p->id))
            ->pluck('activePrice.currency')
            ->filter()
            ->unique()
            ->values();

        if ($currencies->count() > 1) {
            throw new MixedCurrencyException;
        }

        $subtotal = $lines->reduce(
            fn (string $carry, CalculatedLine $line): string => bcadd($carry, $line->lineTotal, 2),
            '0.00',
        );

        return new CartQuoteResult(
            lines: $lines,
            subtotal: $subtotal,
            currency: (string) $currencies->first(),
        );
    }
}
