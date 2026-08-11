<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('receivables:mark-overdue')->dailyAt('00:30');
Schedule::command('reconciliation:run --scope=integral')->dailyAt('01:00');

Schedule::command('reconciliation:run --scope=integral')->dailyAt('01:00');
// (receivables:mark-overdue está registrado a 00:30, MOD-08.)

Schedule::command('kpi:snapshot --period=diario')->dailyAt('02:00');
Schedule::command('kpi:snapshot --period=mensual')->monthlyOn(1, '02:30');
