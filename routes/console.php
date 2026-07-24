<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── KPI 1 · Disponibilidad ────────────────────────────────────────────────────
// El día 1 de cada mes, a las 03:00 (hora de Chile), congela la disponibilidad
// del mes recién cerrado para todos los servicios críticos activos.
Schedule::command('kpi:capturar-disponibilidad')
    ->monthlyOn(1, '03:00')
    ->timezone('America/Santiago')
    ->onOneServer()
    ->withoutOverlapping();
