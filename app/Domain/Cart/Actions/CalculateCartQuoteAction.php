<?php

declare(strict_types=1);

namespace App\Domain\Cart\Actions;

use App\Domain\Cart\Data\CartQuoteData;
use App\Domain\Cart\Data\CartQuoteResult;
use App\Domain\Cart\Services\CartCalculator;
use App\Models\Product;

final class CalculateCartQuoteAction
{
    public function __construct(private readonly CartCalculator $calculator) {}

    public function execute(CartQuoteData $data): CartQuoteResult
    {
        $products = Product::query()
            ->with(['activePrice', 'inventory'])
            ->whereIn('id', $data->items->pluck('productId')->all())
            ->get()
            ->keyBy('id');

        return $this->calculator->calculate($data->items, $products);
    }
}
