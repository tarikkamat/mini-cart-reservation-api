<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Reservation\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'customer_email', 'status', 'subtotal', 'currency', 'expires_at', 'released_at', 'idempotency_key'])]
#[Table(keyType: 'string', incrementing: false)]
#[UseFactory(ReservationFactory::class)]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'subtotal' => 'decimal:2',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Active);
    }

    public function scopeExpirable(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Active)
            ->where('expires_at', '<', now());
    }
}
