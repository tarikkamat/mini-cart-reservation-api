<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Reservation\Actions\CreateReservationAction;
use App\Domain\Reservation\Actions\ReleaseReservationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReservationRequest;
use App\Http\Resources\ReservationResource;
use Dedoc\Scramble\Attributes\Parameter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReservationController extends Controller
{
    /**
     * Create reservation.
     *
     * Atomically reserves stock for the listed items for a configurable TTL
     * (default 15 minutes). Concurrency-safe via PostgreSQL conditional UPDATE
     * + CHECK constraints — stock can never go negative.
     *
     * Pass an `Idempotency-Key` header to make creation safe against retries:
     * the same key + same body returns the cached response with
     * `Idempotent-Replay: true`; the same key + different body returns 422
     * `IDEMPOTENCY_CONFLICT`.
     */
    #[Parameter(
        in: 'header',
        name: 'Idempotency-Key',
        description: 'Optional client-generated UUID. Same key + same body within 24 hours replays the original 201 response with an `Idempotent-Replay: true` header. Same key + different body returns 422 IDEMPOTENCY_CONFLICT.',
        required: false,
        type: 'string',
        format: 'uuid',
        example: '7f3e9d52-1c6a-4ba3-9c45-9a2c1d5b2c11',
    )]
    public function store(CreateReservationRequest $request, CreateReservationAction $action): JsonResponse
    {
        $reservation = $action->execute($request->toData());

        return ReservationResource::make($reservation->load('items.product'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Release reservation.
     *
     * Idempotently releases an active reservation. Calling on an already-released,
     * expired, or committed reservation returns the current state with HTTP 200 —
     * no error, no double-restore of stock.
     */
    public function release(string $id, ReleaseReservationAction $action): ReservationResource
    {
        $reservation = $action->execute($id);

        return ReservationResource::make($reservation->load('items.product'));
    }
}
