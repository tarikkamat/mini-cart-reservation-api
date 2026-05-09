<?php

declare(strict_types=1);

use App\Jobs\ExpireReservationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ExpireReservationsJob)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onFailure(fn () => Log::error('reservation.expiration_job_failed'));
