<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'quantity', 'reserved_quantity', 'version'])]
#[UseFactory(InventoryFactory::class)]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'version' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function available(): Attribute
    {
        return Attribute::get(fn (): int => $this->quantity - $this->reserved_quantity);
    }
}
