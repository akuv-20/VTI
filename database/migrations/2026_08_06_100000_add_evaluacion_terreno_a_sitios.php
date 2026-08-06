<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para evaluar en terreno un sitio que todavía NO tiene enlace.
 *
 * La ficha existente describe un sitio ya operativo (ISP, subred, UPS, equipos).
 * Estos 52 campos que se incorporan no tienen nada: lo que hay que capturar no
 * es qué está instalado sino qué haría falta para llevarles conectividad y a
 * qué costo. De ahí el prefijo `eval_`, que separa el levantamiento de
 * factibilidad de la ficha de operación.
 *
 * Los booleanos van SIN default y nulables a propósito: en una evaluación
 * "todavía no lo miré" y "no hay" son cosas distintas, y un default false las
 * confundiría en los conteos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sitios', function (Blueprint $table) {
            // ── Qué hay hoy en el sitio ──────────────────────────────────────
            $table->string('eval_energia', 20)->nullable()->after('notas');       // no | monofasica | trifasica | generador
            $table->boolean('eval_energia_estable')->nullable()->after('eval_energia');
            $table->boolean('eval_internet_particular')->nullable()->after('eval_energia_estable');
            $table->string('eval_internet_detalle')->nullable()->after('eval_internet_particular');
            $table->text('eval_infra_existente')->nullable()->after('eval_internet_detalle');

            // ── Cobertura móvil, una columna por operador ────────────────────
            // Separadas y no un solo campo porque la pregunta que se responde
            // después es "¿en qué campos funciona Entel?", para decidir qué SIM
            // comprar. En un campo único eso no se puede filtrar.
            $table->string('cob_entel', 12)->nullable()->after('eval_infra_existente');    // sin | mala | regular | buena
            $table->string('cob_movistar', 12)->nullable()->after('cob_entel');
            $table->string('cob_wom', 12)->nullable()->after('cob_movistar');
            $table->string('cob_claro', 12)->nullable()->after('cob_wom');
            $table->text('cob_notas')->nullable()->after('cob_claro');

            // ── Viabilidad del enlace ────────────────────────────────────────
            $table->string('eval_linea_vista', 12)->nullable()->after('cob_notas');  // si | parcial | no
            $table->string('eval_linea_vista_hacia')->nullable()->after('eval_linea_vista');
            $table->decimal('eval_distancia_km', 6, 2)->nullable()->after('eval_linea_vista_hacia');
            $table->boolean('eval_cielo_despejado')->nullable()->after('eval_distancia_km');
            $table->string('eval_fibra_zona', 10)->nullable()->after('eval_cielo_despejado'); // si | no | no_se

            // ── Dónde montar los equipos ─────────────────────────────────────
            $table->string('eval_punto_montaje', 15)->nullable()->after('eval_fibra_zona'); // poste | torre | techo | silo | no_hay
            $table->decimal('eval_altura_m', 5, 2)->nullable()->after('eval_punto_montaje');
            $table->string('eval_sala_equipos', 10)->nullable()->after('eval_altura_m');     // si | caseta | no

            // ── Qué se necesita ──────────────────────────────────────────────
            $table->boolean('eval_necesita_camaras')->nullable()->after('eval_sala_equipos');
            $table->boolean('eval_necesita_wifi')->nullable()->after('eval_necesita_camaras');
            $table->text('eval_uso_previsto')->nullable()->after('eval_necesita_wifi');

            // ── Conclusión de la visita ──────────────────────────────────────
            $table->string('solucion_propuesta', 15)->nullable()->after('eval_uso_previsto');
            $table->unsignedSmallInteger('orden_ejecucion')->nullable()->after('solucion_propuesta');
            $table->decimal('costo_estimado', 12, 0)->nullable()->after('orden_ejecucion');
            $table->text('acciones')->nullable()->after('costo_estimado');
        });

        // Para el tablero: "campos por solución propuesta" y "qué sigue primero".
        Schema::table('sitios', function (Blueprint $table) {
            $table->index(['solucion_propuesta', 'orden_ejecucion']);
        });
    }

    public function down(): void
    {
        Schema::table('sitios', function (Blueprint $table) {
            $table->dropIndex(['solucion_propuesta', 'orden_ejecucion']);
        });

        Schema::table('sitios', function (Blueprint $table) {
            $table->dropColumn([
                'eval_energia', 'eval_energia_estable', 'eval_internet_particular',
                'eval_internet_detalle', 'eval_infra_existente',
                'cob_entel', 'cob_movistar', 'cob_wom', 'cob_claro', 'cob_notas',
                'eval_linea_vista', 'eval_linea_vista_hacia', 'eval_distancia_km',
                'eval_cielo_despejado', 'eval_fibra_zona',
                'eval_punto_montaje', 'eval_altura_m', 'eval_sala_equipos',
                'eval_necesita_camaras', 'eval_necesita_wifi', 'eval_uso_previsto',
                'solucion_propuesta', 'orden_ejecucion', 'costo_estimado', 'acciones',
            ]);
        });
    }
};
