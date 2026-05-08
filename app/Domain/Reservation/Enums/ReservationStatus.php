<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Expired = 'expired';
    case Committed = 'committed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Released => 'Released',
            self::Expired => 'Expired',
            self::Committed => 'Committed',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Active;
    }
}
