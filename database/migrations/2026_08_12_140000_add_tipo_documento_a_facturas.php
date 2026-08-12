<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de crédito.
 *
 * Hasta ahora una NC solo se podía cargar como factura positiva, así que en vez
 * de restar sumaba: el documento 11258436 de junio entró con +199.005 y su
 * descripción decía «Nota de credito», con un desvío real de 398.010.
 *
 * El documento se etiqueta en `tipo_documento` y su importe se guarda NEGATIVO.
 * Así los cinco lugares que suman —listado, resumen por cuenta contable, resumen
 * por servicio, entregas y el total del filtro— restan solos, sin tocarlos.
 *
 * `tipo_documento` es independiente de `tipo`: ese sigue diciendo si el gasto es
 * Mensual o Esporádica, y una NC puede ser contra cualquiera de los dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->enum('tipo_documento', ['Factura', 'Nota de crédito'])
                ->default('Factura')
                ->after('tipo');
        });

        // Lo existente se clasifica por el signo, como pidió Felipe. Hoy no hay
        // ningún negativo —la validación los impedía—, así que en la práctica
        // todo queda como Factura; la regla queda igual por si se cargan datos
        // antes de que esta migración corra en otro ambiente.
        DB::table('facturas')->where('valor_neto', '<', 0)->update(['tipo_documento' => 'Nota de crédito']);
        DB::table('facturas')->where('valor_neto', '>=', 0)->update(['tipo_documento' => 'Factura']);
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('tipo_documento');
        });
    }
};
