<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapa_nodos', function (Blueprint $table) {
            $table->unsignedSmallInteger('icono_px')->default(48)->after('icono'); // tamaño del chip (24-128 px)
            $table->unsignedSmallInteger('letra_px')->default(11)->after('icono_px'); // tamaño de la etiqueta (8-28 px)
        });
    }

    public function down(): void
    {
        Schema::table('mapa_nodos', function (Blueprint $table) {
            $table->dropColumn(['icono_px', 'letra_px']);
        });
    }
};
