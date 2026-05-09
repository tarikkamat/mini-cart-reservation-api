<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Process\Process;

/**
 * Spawn an isolated `php artisan serve` against the test database and wait
 * until it answers /api/health. Returns the process so the caller can stop it.
 */
function spawnTestServer(int $port): Process
{
    $env = [
        'APP_ENV' => 'testing',
        'APP_KEY' => env('APP_KEY'),
        'APP_DEBUG' => 'false',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '5432',
        'DB_DATABASE' => 'mini_cart_test',
        'DB_USERNAME' => 'mini_cart',
        'DB_PASSWORD' => 'secret',
        'CACHE_STORE' => 'redis',
        'SESSION_DRIVER' => 'redis',
        'QUEUE_CONNECTION' => 'sync',
        'REDIS_CLIENT' => 'predis',
        'REDIS_HOST' => '127.0.0.1',
        'REDIS_PORT' => '6379',
        'REDIS_PASSWORD' => 'secret',
    ];

    $process = new Process(
        ['php', 'artisan', 'serve', "--port={$port}", '--no-reload'],
        base_path(),
        $env,
    );
    $process->setTimeout(null);
    $process->start();

    $deadline = microtime(true) + 8.0;
    while (microtime(true) < $deadline) {
        $ctx = stream_context_create(['http' => ['timeout' => 0.5, 'ignore_errors' => true]]);
        $body = @file_get_contents("http://127.0.0.1:{$port}/api/health", false, $ctx);
        if (is_string($body) && str_contains($body, '"status":"ok"')) {
            return $process;
        }
        usleep(150_000);
    }

    $process->stop(0);
    throw new RuntimeException('Test server did not become healthy in time. STDERR: '.$process->getErrorOutput());
}

it('atomically resolves 10 parallel reservation requests against stock = 5', function (): void {
    // Create the test database from a real connection (committed) so the
    // spawned server can see the product and inventory.
    $product = makeProductWithStock(stock: 5);

    $port = 8765;
    $server = spawnTestServer($port);

    try {
        $client = new Client(['base_uri' => "http://127.0.0.1:{$port}", 'http_errors' => false]);

        $requests = function () use ($product) {
            for ($i = 0; $i < 10; $i++) {
                yield new Request(
                    'POST',
                    '/api/reservations',
                    ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                    (string) json_encode([
                        'customer_email' => "user{$i}@x.com",
                        'items' => [['product_id' => $product->id, 'quantity' => 1]],
                    ]),
                );
            }
        };

        $statuses = [];
        $pool = new Pool($client, $requests(), [
            'concurrency' => 10,
            'fulfilled' => function ($response, int $index) use (&$statuses): void {
                $statuses[$index] = $response->getStatusCode();
            },
            'rejected' => function ($reason, int $index) use (&$statuses): void {
                $statuses[$index] = $reason instanceof BadResponseException
                    ? $reason->getResponse()->getStatusCode()
                    : 0;
            },
        ]);
        $pool->promise()->wait();
    } finally {
        $serverPid = $server->getPid();
        $server->stop(0, SIGKILL);
        // `php artisan serve` spawns a `php -S` child. The Process::stop()
        // call kills only the direct child, so we force-kill any orphans
        // still bound to the test port.
        if ($serverPid !== null) {
            @exec("pkill -9 -P {$serverPid} 2>/dev/null");
        }
        @exec('lsof -ti :'.$port.' | xargs -r kill -9 2>/dev/null');
    }

    $created = count(array_filter($statuses, fn (int $s): bool => $s === 201));
    $conflict = count(array_filter($statuses, fn (int $s): bool => $s === 409));

    expect($created)->toBe(5, "expected exactly 5 successful reservations, got {$created}");
    expect($conflict)->toBe(5, "expected exactly 5 INSUFFICIENT_STOCK responses, got {$conflict}");
    expect($created + $conflict)->toBe(10);

    $product->inventory->refresh();
    expect($product->inventory->reserved_quantity)->toBe(5);
    expect($product->inventory->quantity)->toBeGreaterThanOrEqual($product->inventory->reserved_quantity);
});
