<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'reservation_id', 'type', 'quantity_delta', 'quantity_after', 'reserved_after', 'reason'])]
#[Table(timestamps: false)]
#[UseFactory(StockMovementFactory::class)]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'quantity_after' => 'integer',
            'reserved_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
