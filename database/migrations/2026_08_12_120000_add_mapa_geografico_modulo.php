<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo propio para el mapa geográfico.
 *
 * Sale del panel de avance, donde vivía apretado en una tarjeta de 430 px. Va
 * como módulo aparte y no como otra pantalla de Sitios para poder darle acceso
 * a quien solo necesita ver dónde están los campos, sin entregarle además la
 * edición de las fichas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'mapa_geografico'],
            [
                'grupo'          => 'Monitoreo',
                'label'          => 'Mapa geográfico',
                'descripcion'    => 'Ubicación real de los sitios sobre el mapa, agrupados por cercanía y coloreados por estado del enlace',
                'route_prefixes' => json_encode(['admin.mapa-geografico']),
                'orden'          => 57,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );

        // Quien ya tiene Sitios lo da por sentado: el mapa estaba dentro de ese
        // módulo hasta hoy, así que quitárselo seria una regresion para el.
        $sitios = DB::table('modulos')->where('nombre', 'sitios')->first();
        $mapa   = DB::table('modulos')->where('nombre', 'mapa_geografico')->first();

        if ($sitios && $mapa) {
            $usuarios = DB::table('modulo_user')->where('modulo_id', $sitios->id)->pluck('user_id');
            foreach ($usuarios as $userId) {
                DB::table('modulo_user')->updateOrInsert(
                    ['modulo_id' => $mapa->id, 'user_id' => $userId],
                    ['modulo_id' => $mapa->id, 'user_id' => $userId]
                );
            }
        }
    }

    public function down(): void
    {
        $modulo = DB::table('modulos')->where('nombre', 'mapa_geografico')->first();
        if ($modulo) {
            DB::table('modulo_user')->where('modulo_id', $modulo->id)->delete();
            DB::table('modulos')->where('id', $modulo->id)->delete();
        }
    }
};
