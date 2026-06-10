<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('actas_entrega_equipo', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('glpi_computer_id');
            $table->date('fecha_emision');
            $table->string('nombre_equipo');
            $table->string('nombre_receptor')->nullable();
            $table->string('ubicacion')->nullable();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('numero_serie')->nullable();
            $table->string('sistema_operativo')->nullable();
            $table->string('condicion')->default('Usado');
            $table->json('accesorios')->nullable();
            $table->text('observacion')->nullable();
            $table->string('entregado_por')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actas_entrega_equipo');
    }
};
