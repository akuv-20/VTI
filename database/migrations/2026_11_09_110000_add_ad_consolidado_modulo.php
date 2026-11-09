<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'ad_consolidado'],
            [
                'grupo'          => 'Active Directory',
                'label'          => 'Consolidado AD',
                'descripcion'    => 'Inventario consolidado de los dominios AD (entorno y cuentas) para copiar al Excel',
                'route_prefixes' => json_encode(['admin.ad_consolidado.']),
                'orden'          => 44,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }

    public function down(): void
    {
        $m = DB::table('modulos')->where('nombre', 'ad_consolidado')->first();
        if ($m) {
            DB::table('modulo_user')->where('modulo_id', $m->id)->delete();
            DB::table('modulos')->where('id', $m->id)->delete();
        }
    }
};
