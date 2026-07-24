<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapa_enlaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapa_id')->constrained('mapas_red')->cascadeOnDelete();
            $table->foreignId('nodo_a_id')->constrained('mapa_nodos')->cascadeOnDelete();
            $table->foreignId('nodo_b_id')->constrained('mapa_nodos')->cascadeOnDelete();
            $table->string('tipo')->default('cable');    // fibra | cable | inalambrico
            $table->string('etiqueta')->nullable();      // ej: "PtP 5GHz Ubiquiti"
            $table->timestamps();

            $table->index('mapa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapa_enlaces');
    }
};
