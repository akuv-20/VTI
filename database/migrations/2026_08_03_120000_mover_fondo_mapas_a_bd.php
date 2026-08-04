<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * El plano de fondo de los mapas pasa a guardarse en la base de datos.
 *
 * Estaba en storage/app/public, que no viaja en el despliegue: cada paso a
 * producción borraba los planos. En la base viaja con el respaldo y sobrevive.
 *
 * Va en su propia tabla y no como columna de `mapas_red` para que el listado
 * de mapas siga siendo liviano: el blob solo se lee cuando se sirve la imagen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapa_fondos', function (Blueprint $table) {
            $table->foreignId('mapa_id')->constrained('mapas_red')->cascadeOnDelete();
            $table->string('mime', 80);
            $table->unsignedInteger('bytes');
            $table->binary('imagen');
            $table->timestamps();

            $table->primary('mapa_id');
        });

        // binary() crea un BLOB de 64 KB; un plano necesita bastante más.
        DB::statement('ALTER TABLE mapa_fondos MODIFY imagen MEDIUMBLOB NOT NULL');

        Schema::table('mapas_red', function (Blueprint $table) {
            // Marca que hay plano y sirve de versión para el caché del navegador.
            $table->timestamp('fondo_actualizado_at')->nullable()->after('fondo_opacidad');
        });

        // Traslada los planos que todavía estén en disco.
        $disco = Storage::disk('public');
        foreach (DB::table('mapas_red')->whereNotNull('imagen_fondo')->get(['id', 'imagen_fondo']) as $mapa) {
            if (!$disco->exists($mapa->imagen_fondo)) continue;

            $contenido = $disco->get($mapa->imagen_fondo);

            DB::table('mapa_fondos')->insert([
                'mapa_id'    => $mapa->id,
                'mime'       => $disco->mimeType($mapa->imagen_fondo) ?: 'image/jpeg',
                'bytes'      => strlen($contenido),
                'imagen'     => $contenido,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('mapas_red')->where('id', $mapa->id)->update(['fondo_actualizado_at' => now()]);
        }

        Schema::table('mapas_red', function (Blueprint $table) {
            $table->dropColumn('imagen_fondo');
        });
    }

    public function down(): void
    {
        Schema::table('mapas_red', function (Blueprint $table) {
            $table->string('imagen_fondo')->nullable()->after('fondo_opacidad');
        });

        // Devuelve los planos al disco para no perderlos al revertir.
        $disco = Storage::disk('public');
        foreach (DB::table('mapa_fondos')->get() as $fondo) {
            $ext  = explode('/', $fondo->mime)[1] ?? 'jpg';
            $ruta = 'mapas_red/fondo_' . $fondo->mapa_id . '.' . $ext;
            $disco->put($ruta, $fondo->imagen);
            DB::table('mapas_red')->where('id', $fondo->mapa_id)->update(['imagen_fondo' => $ruta]);
        }

        Schema::dropIfExists('mapa_fondos');

        Schema::table('mapas_red', function (Blueprint $table) {
            $table->dropColumn('fondo_actualizado_at');
        });
    }
};
