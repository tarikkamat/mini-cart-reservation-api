<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Reservation\Exceptions\IdempotencyConflictException;
use App\Support\Idempotency\IdempotencyStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyMiddleware
{
    public function __construct(private readonly IdempotencyStore $store) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '') {
            return $next($request);
        }

        $hash = hash('sha256', (string) $request->getContent());

        return Cache::lock("idem-lock:{$key}", 10)->block(5, function () use ($key, $hash, $request, $next): Response {
            $cached = $this->store->get($key);

            if ($cached !== null) {
                if ($cached['hash'] !== $hash) {
                    throw new IdempotencyConflictException;
                }

                return response($cached['body'], $cached['status'])
                    ->header('Content-Type', 'application/json')
                    ->header('Idempotent-Replay', 'true');
            }

            $response = $next($request);

            // Cache 2xx and 4xx responses; never cache server errors so a transient
            // failure does not poison the key.
            if ($response->getStatusCode() < 500) {
                $this->store->put($key, $hash, $response->getStatusCode(), (string) $response->getContent());
            }

            return $response;
        });
    }
}
