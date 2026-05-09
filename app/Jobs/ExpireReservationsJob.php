<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Reservation\Actions\ExpireReservationAction;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ExpireReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(ExpireReservationAction $action): void
    {
        $expired = 0;

        Reservation::query()
            ->expirable()
            ->orderBy('id')
            ->chunkById(100, function ($reservations) use ($action, &$expired): void {
                foreach ($reservations as $reservation) {
                    $result = $action->execute($reservation->id);
                    if ($result?->status->value === 'expired') {
                        $expired++;
                    }
                }
            });

        if ($expired > 0) {
            Log::info('reservation.expired_batch', ['count' => $expired]);
        }
    }
}
