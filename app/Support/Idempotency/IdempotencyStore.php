<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Redis\RedisManager;

final class IdempotencyStore
{
    public function __construct(private readonly RedisManager $redis) {}

    /**
     * @return array{hash: string, status: int, body: string}|null
     */
    public function get(string $key): ?array
    {
        $raw = $this->redis->connection()->get($this->redisKey($key));
        if (! is_string($raw)) {
            return null;
        }

        /** @var array{hash: string, status: int, body: string}|null $decoded */
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function put(string $key, string $hash, int $status, string $body): void
    {
        $ttl = (int) config('reservation.idempotency_ttl_hours') * 3600;

        $this->redis->connection()->setex(
            $this->redisKey($key),
            $ttl,
            (string) json_encode([
                'hash' => $hash,
                'status' => $status,
                'body' => $body,
            ]),
        );
    }

    private function redisKey(string $key): string
    {
        return "idem:{$key}";
    }
}
