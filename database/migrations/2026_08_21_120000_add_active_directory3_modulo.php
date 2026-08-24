<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'active_directory3'],
            [
                'grupo'          => 'Active Directory',
                'label'          => 'AD Unifrutti',
                'descripcion'    => 'Gestión de usuarios del AD Unifrutti',
                'route_prefixes' => json_encode(['admin.active_directory3.']),
                'orden'          => 42,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );

        // Otorgar acceso a quienes ya administran el AD Grupo Verfrut (Perú)
        $ad2 = DB::table('modulos')->where('nombre', 'active_directory2')->first();
        $ad3 = DB::table('modulos')->where('nombre', 'active_directory3')->first();

        if ($ad2 && $ad3) {
            $userIds = DB::table('modulo_user')->where('modulo_id', $ad2->id)->pluck('user_id');
            foreach ($userIds as $userId) {
                DB::table('modulo_user')->insertOrIgnore([
                    'modulo_id' => $ad3->id,
                    'user_id'   => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ad3 = DB::table('modulos')->where('nombre', 'active_directory3')->first();
        if ($ad3) {
            DB::table('modulo_user')->where('modulo_id', $ad3->id)->delete();
            DB::table('modulos')->where('id', $ad3->id)->delete();
        }
    }
};
