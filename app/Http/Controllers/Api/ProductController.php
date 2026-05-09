<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Product\Data\ProductFilters;
use App\Domain\Product\Queries\ProductListQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * List products.
     *
     * Returns a paginated list of products with active price and real-time available stock.
     * Uses PostgreSQL `ILIKE` for case-insensitive search on `name` and `sku`. Eager-loads
     * `activePrice` and `inventory` to keep the listing N+1 free (≤ 4 queries regardless of
     * result count).
     */
    public function index(Request $request, ProductListQuery $query): AnonymousResourceCollection
    {
        $filters = new ProductFilters(
            search: $request->query('search') ? (string) $request->query('search') : null,
            isActive: $request->boolean('is_active', true),
            sort: (string) $request->query('sort', ProductFilters::SORT_NEWEST),
            perPage: (int) $request->query('per_page', (string) ProductFilters::DEFAULT_PER_PAGE),
            page: (int) $request->query('page', '1'),
        );

        return ProductResource::collection($query->execute($filters));
    }
}
