<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'currency', 'amount', 'is_active', 'valid_from', 'valid_to'])]
#[UseFactory(ProductPriceFactory::class)]
class ProductPrice extends Model
{
    /** @use HasFactory<ProductPriceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
