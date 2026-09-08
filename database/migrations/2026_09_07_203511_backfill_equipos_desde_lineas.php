<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Crea un equipo (propiedad Empresa) por cada línea que hoy tenga aparato o IMEI,
     * copiando modelo/IMEI/usuario/ubicación, y la enlaza vía id_equipo.
     * Las líneas sin aparato ni IMEI quedan sin equipo ("solo chip").
     */
    public function up(): void
    {
        $lineas = DB::table('lineas_telefonicas')
            ->whereNull('id_equipo')
            ->where(function ($q) {
                $q->whereNotNull('id_aparato')
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('imei_equipo')->where('imei_equipo', '!=', '');
                  });
            })
            ->get(['id', 'id_aparato', 'imei_equipo', 'id_usuario', 'id_ubicacion']);

        foreach ($lineas as $l) {
            $equipoId = DB::table('equipos')->insertGetId([
                'id_aparato'   => $l->id_aparato,
                'imei'         => $l->imei_equipo ?: null,
                'propiedad'    => 'Empresa',
                'id_usuario'   => $l->id_usuario,
                'id_ubicacion' => $l->id_ubicacion,
                'estado'       => 'En uso',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::table('lineas_telefonicas')->where('id', $l->id)->update(['id_equipo' => $equipoId]);
        }
    }

    public function down(): void
    {
        // Desliga los equipos backfilleados y los elimina (los que están ligados a una línea).
        $ids = DB::table('lineas_telefonicas')->whereNotNull('id_equipo')->pluck('id_equipo');
        DB::table('lineas_telefonicas')->whereNotNull('id_equipo')->update(['id_equipo' => null]);
        DB::table('equipos')->whereIn('id', $ids)->delete();
    }
};
