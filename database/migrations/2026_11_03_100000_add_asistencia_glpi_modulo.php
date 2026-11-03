<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'asistencia_glpi'],
            [
                'grupo'          => 'Asistencia GLPI',
                'label'          => 'Ranking y asignación de tickets',
                'descripcion'    => 'Ranking de técnicos y mesa de asignación de tickets del GLPI de Unifrutti (asignar exige perfil Supervisor, Admin o Super-Admin en GLPI)',
                'route_prefixes' => json_encode(['asistencia.']),
                'orden'          => 55,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }

    public function down(): void
    {
        $modulo = DB::table('modulos')->where('nombre', 'asistencia_glpi')->first();
        if ($modulo) {
            DB::table('modulo_user')->where('modulo_id', $modulo->id)->delete();
            DB::table('modulos')->where('id', $modulo->id)->delete();
        }
    }
};
