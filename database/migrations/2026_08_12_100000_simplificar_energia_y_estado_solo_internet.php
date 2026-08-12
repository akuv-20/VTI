<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajusta el levantamiento a lo que de verdad se llena en terreno.
 *
 * `eval_energia` deja de distinguir monofásica, trifásica y generador: en los 12
 * levantamientos de Linderos y Maipú esa distinción no se usó (9 quedaron sin
 * responder) y para decidir un enlace solo importa si hay energía o no.
 *
 * No se borra ninguna columna. Lo que sale del formulario móvil sigue existiendo
 * y se edita desde la ficha de escritorio, así que los datos de los 12
 * levantamientos quedan intactos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cualquier tipo de suministro pasa a ser «sí hay». `no` ya significaba
        // lo mismo en el esquema viejo y no se toca.
        DB::table('sitios')
            ->whereIn('eval_energia', ['monofasica', 'trifasica', 'generador'])
            ->update(['eval_energia' => 'si']);
    }

    public function down(): void
    {
        // El detalle original no se puede recuperar: «sí» no sabe si venía de
        // monofásica, trifásica o generador. Se devuelve al valor más frecuente
        // en los datos reales, que era trifásica.
        DB::table('sitios')
            ->where('eval_energia', 'si')
            ->update(['eval_energia' => 'trifasica']);
    }
};
