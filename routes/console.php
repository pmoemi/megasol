<?php

use App\Services\System\CronHealthService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    app(CronHealthService::class)->recordHeartbeat();
})->everyMinute()->name('scheduler-heartbeat');

Schedule::command('sms:run-automations')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('paygro:sync', ['--source' => 'scheduled'])
    ->daily()
    ->withoutOverlapping(600)
    ->onOneServer()
    ->name('paygro-daily-sync')
    ->runInBackground();
