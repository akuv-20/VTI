<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'kpi_disponibilidad'],
            [
                'grupo'          => 'KPIs',
                'label'          => 'KPI Disponibilidad',
                'descripcion'    => 'KPI 1: disponibilidad de servicios críticos vía CheckMK, con reporte mensual y anual',
                'route_prefixes' => json_encode(['admin.kpi.disponibilidad.']),
                'orden'          => 50,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }

    public function down(): void
    {
        $modulo = DB::table('modulos')->where('nombre', 'kpi_disponibilidad')->first();
        if ($modulo) {
            DB::table('modulo_user')->where('modulo_id', $modulo->id)->delete();
            DB::table('modulos')->where('id', $modulo->id)->delete();
        }
    }
};
