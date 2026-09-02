<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'dhcp'],
            [
                'grupo'          => 'Redes',
                'label'          => 'DHCP / Reservas',
                'descripcion'    => 'Reservas DHCP, uso del pool de IPs y alertas de inactividad',
                'route_prefixes' => json_encode(['dhcp.']),
                'orden'          => 50,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('modulos')->where('nombre', 'dhcp')->delete();
    }
};
