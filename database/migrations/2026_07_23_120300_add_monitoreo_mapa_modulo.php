<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'monitoreo_mapa'],
            [
                'grupo'          => 'Monitoreo',
                'label'          => 'Mapa de red',
                'descripcion'    => 'Mapa visual de la red en vivo: hosts, enlaces y estado desde CheckMK, con modo TV',
                'route_prefixes' => json_encode(['admin.monitoreo.']),
                'orden'          => 55,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }

    public function down(): void
    {
        $modulo = DB::table('modulos')->where('nombre', 'monitoreo_mapa')->first();
        if ($modulo) {
            DB::table('modulo_user')->where('modulo_id', $modulo->id)->delete();
            DB::table('modulos')->where('id', $modulo->id)->delete();
        }
    }
};
