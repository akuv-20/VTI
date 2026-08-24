<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'inventario_unifrutti'],
            [
                'grupo'          => 'Inventario Unifrutti',
                'label'          => 'Cruce AD ↔ GLPI',
                'descripcion'    => 'Cruce de equipos del AD Unifrutti contra el inventario del GLPI Helpdesk',
                'route_prefixes' => json_encode(['admin.inventario_unifrutti.']),
                'orden'          => 43,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );

        // Otorgar acceso a quienes ya administran el Inventario TI actual
        $inv = DB::table('modulos')->where('nombre', 'inventario_ti')->first();
        $uni = DB::table('modulos')->where('nombre', 'inventario_unifrutti')->first();

        if ($inv && $uni) {
            $userIds = DB::table('modulo_user')->where('modulo_id', $inv->id)->pluck('user_id');
            foreach ($userIds as $userId) {
                DB::table('modulo_user')->insertOrIgnore([
                    'modulo_id' => $uni->id,
                    'user_id'   => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $uni = DB::table('modulos')->where('nombre', 'inventario_unifrutti')->first();
        if ($uni) {
            DB::table('modulo_user')->where('modulo_id', $uni->id)->delete();
            DB::table('modulos')->where('id', $uni->id)->delete();
        }
    }
};
