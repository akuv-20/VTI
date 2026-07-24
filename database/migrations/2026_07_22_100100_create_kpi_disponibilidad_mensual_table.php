<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_disponibilidad_mensual', function (Blueprint $table) {
            $table->id();

            // Referencia al servicio crítico. Guardamos también host/servicio
            // "aplanados" para que el histórico sobreviva si el crítico se elimina.
            $table->foreignId('servicio_id')->nullable()
                  ->constrained('kpi_servicios_criticos')->nullOnDelete();
            $table->string('host_name');
            $table->string('service_description')->nullable();

            $table->unsignedSmallInteger('anio');   // 2027, ...
            $table->unsignedTinyInteger('mes');      // 1-12

            // Tiempos en segundos del periodo evaluado.
            $table->unsignedBigInteger('up_seconds')->default(0);          // OK / warning contabilizados como arriba
            $table->unsignedBigInteger('down_seconds')->default(0);        // caídas reales (cuentan como indisponibilidad)
            $table->unsignedBigInteger('unmonitored_seconds')->default(0); // sin datos / no monitoreado
            $table->unsignedBigInteger('downtime_seconds')->default(0);    // mantenimientos programados (se excluyen del denominador)

            // % de disponibilidad ya calculado y congelado.
            $table->decimal('pct', 5, 2)->nullable();

            $table->string('fuente')->default('checkmk_api'); // checkmk_api | manual
            $table->text('nota')->nullable();                  // observación (ej. ajuste manual, incidente)
            $table->timestamp('capturado_en')->nullable();
            $table->timestamps();

            $table->unique(['host_name', 'service_description', 'anio', 'mes'], 'kpi_disp_periodo_unique');
            $table->index(['anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_disponibilidad_mensual');
    }
};
