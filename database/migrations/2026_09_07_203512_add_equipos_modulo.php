<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'equipos'],
            [
                'grupo'          => 'Telefonía',
                'label'          => 'Equipos',
                'descripcion'    => 'Inventario de equipos móviles (con o sin línea, empresa o personal)',
                'route_prefixes' => json_encode(['equipos.']),
                'orden'          => 24,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );

        // Otorgar acceso a quienes ya tienen el módulo de líneas telefónicas
        $lineas  = DB::table('modulos')->where('nombre', 'lineas_telefonicas')->first();
        $equipos = DB::table('modulos')->where('nombre', 'equipos')->first();

        if ($lineas && $equipos) {
            $userIds = DB::table('modulo_user')->where('modulo_id', $lineas->id)->pluck('user_id');
            foreach ($userIds as $userId) {
                DB::table('modulo_user')->insertOrIgnore([
                    'modulo_id' => $equipos->id,
                    'user_id'   => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $equipos = DB::table('modulos')->where('nombre', 'equipos')->first();
        if ($equipos) {
            DB::table('modulo_user')->where('modulo_id', $equipos->id)->delete();
            DB::table('modulos')->where('id', $equipos->id)->delete();
        }
    }
};
