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

// ── DHCP · Revisión de reservas inactivas ─────────────────────────────────────
// Todos los días a las 08:00 revisa reservas sin actividad sobre el umbral y,
// si hay un correo configurado, envía el listado para depurar.
Schedule::command('dhcp:revisar-inactivas')
    ->dailyAt('08:00')
    ->timezone('America/Santiago')
    ->onOneServer()
    ->withoutOverlapping();

// ── Entra ID · Actividad de buzones y estado de MFA ───────────────────────────
// Cada 15 minutos rehace en el servidor todo lo que sale de Graph, para que las
// pantallas encuentren la caché caliente y nadie espere el minuto largo que toma
// construirlo. `withoutOverlapping` importa aquí: la corrida dura ~70 s y sin esa
// guardia dos vueltas lentas podrían pisarse.
//
// Los comandos `buzones:analizar` y `mfa:analizar` siguen existiendo para
// recalcular una sola pieza a mano.
Schedule::command('entra:refrescar --silencioso')
    ->everyFifteenMinutes()
    ->timezone('America/Santiago')
    ->onOneServer()
    ->withoutOverlapping(20);
