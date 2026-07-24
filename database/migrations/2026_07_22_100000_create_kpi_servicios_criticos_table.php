<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_servicios_criticos', function (Blueprint $table) {
            $table->id();
            $table->string('host_name');                        // nombre del host en CheckMK
            $table->string('service_description')->nullable();  // servicio; null = disponibilidad del host completo
            $table->string('etiqueta');                         // nombre legible para el informe
            $table->string('grupo')->nullable();                // agrupador para el informe (ej. "Datacenter", "Red")
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            // Un host+servicio no se puede marcar dos veces. Usamos un hash del
            // servicio porque service_description puede ser largo y nullable.
            $table->unique(['host_name', 'service_description'], 'kpi_srv_host_svc_unique');
            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_servicios_criticos');
    }
};
