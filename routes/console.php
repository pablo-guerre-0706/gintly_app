<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('receivables:mark-overdue')->dailyAt('00:30'); // MOD-08.
// MOD-11 · Auditoría de fondo diaria por negocio (RF-11-04). Registro ÚNICO (se eliminó el duplicado).
Schedule::command('reconciliation:run --scope=integral')->dailyAt('01:00');

// MOD-12 · Recálculo de KPIs (RF-12-03). withoutOverlapping evita que dos corridas del mismo
// período se pisen si una tarda de más. Exactamente dos entradas, sin duplicados.
Schedule::command('kpi:snapshot --period=diario')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('kpi:snapshot --period=mensual')->monthlyOn(1, '02:30')->withoutOverlapping();

// MOD-SUB · Reconciliación de suscripciones (RF-SUB). Horaria: vence vigencias, aplica descensos programados,
// reproduce webhooks propios aparcados y recupera pagos cuyo webhook se perdió (consulta oficial al proveedor).
// Reutiliza el scheduler existente; sin colas nuevas. withoutOverlapping evita solaparse si una corrida tarda.
Schedule::command('billing:reconcile')->hourly()->withoutOverlapping();
