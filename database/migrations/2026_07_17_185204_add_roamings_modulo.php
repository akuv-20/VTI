<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'roamings'],
            [
                'grupo'          => 'Telefonía',
                'label'          => 'Roamings',
                'descripcion'    => 'Roaming internacional: pasaportes Movistar, recurrentes y activaciones Entel',
                'route_prefixes' => json_encode(['roamings.']),
                'orden'          => 23,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );

        // Otorgar acceso a quienes ya tienen el módulo de líneas telefónicas
        $lineas   = DB::table('modulos')->where('nombre', 'lineas_telefonicas')->first();
        $roamings = DB::table('modulos')->where('nombre', 'roamings')->first();

        if ($lineas && $roamings) {
            $userIds = DB::table('modulo_user')->where('modulo_id', $lineas->id)->pluck('user_id');
            foreach ($userIds as $userId) {
                DB::table('modulo_user')->insertOrIgnore([
                    'modulo_id' => $roamings->id,
                    'user_id'   => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $roamings = DB::table('modulos')->where('nombre', 'roamings')->first();
        if ($roamings) {
            DB::table('modulo_user')->where('modulo_id', $roamings->id)->delete();
            DB::table('modulos')->where('id', $roamings->id)->delete();
        }
    }
};
