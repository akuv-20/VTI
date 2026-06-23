<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'actas_devolucion_telefono'],
            [
                'grupo'          => 'Telefonía',
                'label'          => 'Actas de Devolución',
                'descripcion'    => 'Actas de devolución de teléfonos',
                'route_prefixes' => json_encode(['actas_devolucion_telefono.']),
                'orden'          => 22,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );

        // Otorgar acceso a quienes ya tienen el módulo de actas de entrega
        $entrega    = DB::table('modulos')->where('nombre', 'actas_entrega_telefono')->first();
        $devolucion = DB::table('modulos')->where('nombre', 'actas_devolucion_telefono')->first();

        if ($entrega && $devolucion) {
            $userIds = DB::table('modulo_user')->where('modulo_id', $entrega->id)->pluck('user_id');
            foreach ($userIds as $userId) {
                DB::table('modulo_user')->insertOrIgnore([
                    'modulo_id' => $devolucion->id,
                    'user_id'   => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $devolucion = DB::table('modulos')->where('nombre', 'actas_devolucion_telefono')->first();
        if ($devolucion) {
            DB::table('modulo_user')->where('modulo_id', $devolucion->id)->delete();
            DB::table('modulos')->where('id', $devolucion->id)->delete();
        }
    }
};
