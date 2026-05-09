<?php

declare(strict_types=1);

namespace App\Domain\Product\Queries;

use App\Domain\Product\Data\ProductFilters;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ProductListQuery
{
    public function execute(ProductFilters $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['activePrice', 'inventory'])
            ->where('is_active', $filters->isActive);

        if ($filters->search !== null && $filters->search !== '') {
            $term = '%'.$filters->search.'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'ilike', $term)->orWhere('sku', 'ilike', $term);
            });
        }

        $query = match ($filters->sort) {
            ProductFilters::SORT_PRICE_ASC => $query->orderBy(
                ProductPrice::query()
                    ->select('amount')
                    ->whereColumn('product_id', 'products.id')
                    ->where('is_active', true)
                    ->limit(1),
                'asc',
            ),
            ProductFilters::SORT_PRICE_DESC => $query->orderBy(
                ProductPrice::query()
                    ->select('amount')
                    ->whereColumn('product_id', 'products.id')
                    ->where('is_active', true)
                    ->limit(1),
                'desc',
            ),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate(
            perPage: min($filters->perPage, ProductFilters::MAX_PER_PAGE),
            page: $filters->page,
        );
    }
}
