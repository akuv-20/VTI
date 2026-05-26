<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('modulos')->where('nombre', 'active_directory')->exists()) {
            $orden = (DB::table('modulos')->max('orden') ?? 0) + 1;

            DB::table('modulos')->insert([
                'nombre'         => 'active_directory',
                'label'          => 'Active Directory',
                'descripcion'    => 'Gestión de usuarios del Active Directory corporativo.',
                'route_prefixes' => json_encode(['admin.active_directory.']),
                'orden'          => $orden,
                'activo'         => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('modulos')->where('nombre', 'active_directory')->delete();
    }
};
