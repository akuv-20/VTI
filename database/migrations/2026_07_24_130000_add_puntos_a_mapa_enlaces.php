<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapa_enlaces', function (Blueprint $table) {
            // Vértices intermedios [{x,y},…] para doblar el enlace ortogonalmente.
            // null / vacío = línea recta (comportamiento anterior).
            $table->json('puntos')->nullable()->after('etiqueta_color');
        });
    }

    public function down(): void
    {
        Schema::table('mapa_enlaces', function (Blueprint $table) {
            $table->dropColumn('puntos');
        });
    }
};
