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
        Schema::create('importaciones_movistar_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_importacion')->constrained('importaciones_movistar')->cascadeOnDelete();
            $table->string('numero_servicio');
            $table->string('plan_tarifario')->nullable();
            $table->string('producto')->nullable();
            $table->decimal('monto', 12, 2)->nullable();
            $table->foreignId('id_linea_telefonica')->nullable()->constrained('lineas_telefonicas')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importaciones_movistar_detalle');
    }
};
