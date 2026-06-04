<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones_wom', function (Blueprint $table) {
            $table->id();
            $table->string('factura', 100);
            $table->tinyInteger('periodo_mes');
            $table->smallInteger('periodo_anio');
            $table->date('fecha_emision')->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedInteger('total_lineas')->default(0);
            $table->timestamps();
        });

        Schema::create('importaciones_wom_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_importacion')
                  ->constrained('importaciones_wom')
                  ->cascadeOnDelete();
            $table->foreignId('id_linea_telefonica')
                  ->constrained('lineas_telefonicas')
                  ->restrictOnDelete();
            $table->decimal('monto', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_wom_detalle');
        Schema::dropIfExists('importaciones_wom');
    }
};
