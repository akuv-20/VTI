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
        Schema::create('importaciones_entel', function (Blueprint $table) {
            $table->id();
            $table->string('folio');
            $table->enum('tipo_servicio', ['Movil', 'BAM']);
            $table->string('codigo_servicio');
            $table->string('periodo_cobro');
            $table->unsignedSmallInteger('periodo_anio');
            $table->unsignedTinyInteger('periodo_mes');
            $table->string('archivo_nombre');
            $table->unsignedInteger('total_lineas')->default(0);
            $table->timestamps();

            $table->unique(['folio', 'tipo_servicio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importaciones_entel');
    }
};
