<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Health check.
     *
     * Pings PostgreSQL and Redis. Returns 200 when both are healthy, 503 otherwise.
     */
    public function __invoke(RedisManager $redis): JsonResponse
    {
        $db = $this->ping(fn (): mixed => DB::connection()->select('SELECT 1'));
        $cache = $this->ping(fn (): mixed => $redis->connection()->ping());

        $ok = $db && $cache;

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'db' => $db ? 'ok' : 'down',
            'redis' => $cache ? 'ok' : 'down',
        ], $ok ? 200 : 503);
    }

    private function ping(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
