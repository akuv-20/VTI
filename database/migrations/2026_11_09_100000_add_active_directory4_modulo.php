<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->updateOrInsert(
            ['nombre' => 'active_directory4'],
            [
                'grupo'          => 'Active Directory',
                'label'          => 'AD Unistgont',
                'descripcion'    => 'Gestión de usuarios del AD Unistgont (unistgont.local)',
                'route_prefixes' => json_encode(['admin.active_directory4.']),
                'orden'          => 43,
                'activo'         => true,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );

        // Otorgar acceso a quienes ya administran el AD Unifrutti
        $ad3 = DB::table('modulos')->where('nombre', 'active_directory3')->first();
        $ad4 = DB::table('modulos')->where('nombre', 'active_directory4')->first();

        if ($ad3 && $ad4) {
            $userIds = DB::table('modulo_user')->where('modulo_id', $ad3->id)->pluck('user_id');
            foreach ($userIds as $userId) {
                DB::table('modulo_user')->insertOrIgnore([
                    'modulo_id' => $ad4->id,
                    'user_id'   => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ad4 = DB::table('modulos')->where('nombre', 'active_directory4')->first();
        if ($ad4) {
            DB::table('modulo_user')->where('modulo_id', $ad4->id)->delete();
            DB::table('modulos')->where('id', $ad4->id)->delete();
        }
    }
};
