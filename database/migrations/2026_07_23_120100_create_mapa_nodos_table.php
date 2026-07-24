<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapa_nodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapa_id')->constrained('mapas_red')->cascadeOnDelete();
            $table->string('host_name')->nullable();       // host CheckMK; null = nodo decorativo/agrupador
            $table->string('etiqueta');                    // texto visible bajo el icono
            $table->string('icono')->default('bi-hdd-network'); // clase Bootstrap Icons
            // Posición en el lienzo virtual (1600×900); se escala al tamaño real.
            $table->float('x')->default(800);
            $table->float('y')->default(450);
            // Drill-down: al hacer clic, abre otro mapa (ej: General → Planta Rapel).
            // Si además no tiene host propio, su estado se agrega desde ese mapa.
            $table->foreignId('mapa_destino_id')->nullable()->constrained('mapas_red')->nullOnDelete();
            $table->timestamps();

            $table->index(['mapa_id', 'host_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapa_nodos');
    }
};
