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
        Schema::create('actas_devolucion_telefono', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_linea_telefonica')->constrained('lineas_telefonicas')->cascadeOnDelete();
            $table->date('fecha_emision');
            $table->string('numero_telefono');
            $table->string('nombre_receptor')->nullable();
            $table->string('zona')->nullable();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('compania')->nullable();
            $table->string('imei_equipo')->nullable();
            $table->string('imei_sim')->nullable();
            $table->string('condicion')->default('Usado');
            $table->json('accesorios')->nullable();
            $table->json('documentacion')->nullable();
            $table->text('observacion')->nullable();
            $table->string('impreso_por')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actas_devolucion_telefono');
    }
};
