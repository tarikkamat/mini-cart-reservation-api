<?php

declare(strict_types=1);

it('returns 200 with db and redis ok', function (): void {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'db' => 'ok',
            'redis' => 'ok',
        ]);
});
