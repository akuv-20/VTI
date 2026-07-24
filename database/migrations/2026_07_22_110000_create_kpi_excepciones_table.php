<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_excepciones', function (Blueprint $table) {
            $table->id();
            $table->string('host_name');                        // host al que aplica
            $table->string('service_description')->nullable();  // servicio; null = aplica al host completo
            $table->dateTime('desde');                          // inicio de la ventana justificada
            $table->dateTime('hasta');                          // fin de la ventana justificada
            $table->string('categoria')->nullable();            // ej. "Corte eléctrico", "Falla ISP"
            $table->text('justificacion');                      // por qué se descuenta del KPI
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['host_name', 'desde', 'hasta']);
            $table->index('activa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_excepciones');
    }
};
