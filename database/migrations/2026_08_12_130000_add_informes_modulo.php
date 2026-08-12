<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo de informes.
 *
 * Grupo propio y no dentro de Monitoreo: acá va a crecer con los informes de
 * otros módulos, no solo con la tabla de sitios.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'informes'],
            [
                'grupo'          => 'Informes',
                'label'          => 'Informes',
                'descripcion'    => 'Tablas completas y exportables a Excel, mostrando solo las columnas que tienen datos',
                'route_prefixes' => json_encode(['admin.informes.']),
                'orden'          => 70,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }

    public function down(): void
    {
        $modulo = DB::table('modulos')->where('nombre', 'informes')->first();
        if ($modulo) {
            DB::table('modulo_user')->where('modulo_id', $modulo->id)->delete();
            DB::table('modulos')->where('id', $modulo->id)->delete();
        }
    }
};
