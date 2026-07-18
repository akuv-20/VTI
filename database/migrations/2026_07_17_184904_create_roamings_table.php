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
        Schema::create('roamings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_linea_telefonica')->constrained('lineas_telefonicas')->cascadeOnDelete();
            $table->string('numero');                  // snapshot del número de línea
            $table->string('nombre_usuario')->nullable(); // snapshot del usuario al agendar
            $table->string('carrier');                 // movistar | entel
            $table->string('tipo');                    // pasaporte | recurrente | entel_uso
            $table->unsignedSmallInteger('pasaporte_dias')->nullable(); // 1,3,7,15,21 (null para recurrente/entel)
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_termino')->nullable(); // calculada solo para pasaporte
            $table->string('destino')->nullable();
            $table->string('id_solicitud')->nullable();
            $table->string('estado')->default('activo'); // activo | cerrado | archivado
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['carrier', 'tipo', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roamings');
    }
};
