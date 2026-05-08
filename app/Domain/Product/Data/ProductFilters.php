<?php

declare(strict_types=1);

namespace App\Domain\Product\Data;

final readonly class ProductFilters
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public const SORT_NEWEST = 'newest';

    public const SORT_PRICE_ASC = 'price_asc';

    public const SORT_PRICE_DESC = 'price_desc';

    public function __construct(
        public ?string $search = null,
        public bool $isActive = true,
        public string $sort = self::SORT_NEWEST,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public int $page = 1,
    ) {}
}
