<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler (Part 4.7) — replaces lazy-only expiry, browser schedulers
| and the unauthenticated external cron (D-016, D-017, D-105).
|--------------------------------------------------------------------------
*/

Schedule::command('orders:expire-holds')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('orders:expire-cash-holds')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('invoices:dispatch')->everyTenSeconds()->withoutOverlapping()->onOneServer();
Schedule::command('visitors:prune')->everyMinute()->withoutOverlapping();
Schedule::command('sadad:reconcile-pending')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('sadad:sync-transactions')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('stock:verify')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('model:prune', ['--model' => [\App\Models\LoginAttempt::class, \App\Models\ActiveVisitor::class]])->dailyAt('00:00')->timezone('Asia/Qatar');
Schedule::command('activitylog:clean')->daily();
Schedule::command('exports:clean')->hourly();
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily();
Schedule::command('backup:clean')->dailyAt('02:00')->timezone('Asia/Qatar');
Schedule::command('backup:run')->dailyAt('02:30')->timezone('Asia/Qatar');
Schedule::command('horizon:snapshot')->everyFiveMinutes()->when(fn () => class_exists(\Laravel\Horizon\Horizon::class) && config('queue.default') === 'redis');
