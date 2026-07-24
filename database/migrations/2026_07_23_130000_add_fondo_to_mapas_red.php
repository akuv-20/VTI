<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapas_red', function (Blueprint $table) {
            $table->string('imagen_fondo')->nullable()->after('descripcion');          // path en disco public (ej: plano de la planta)
            $table->unsignedTinyInteger('fondo_opacidad')->default(40)->after('imagen_fondo'); // 10-100 %
        });
    }

    public function down(): void
    {
        Schema::table('mapas_red', function (Blueprint $table) {
            $table->dropColumn(['imagen_fondo', 'fondo_opacidad']);
        });
    }
};
