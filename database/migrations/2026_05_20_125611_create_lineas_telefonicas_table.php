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
        Schema::create('lineas_telefonicas', function (Blueprint $table) {
            $table->id();
            $table->enum('estado', ['Activo', 'Inactivo'])->default('Activo');
            $table->boolean('vigencia')->default(false);
            $table->string('linea');
            $table->foreignId('id_emisor')->nullable()->constrained('emisores')->nullOnDelete();
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios_telefonicos')->nullOnDelete();
            $table->foreignId('id_empresa')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('id_ubicacion')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->foreignId('id_aparato')->nullable()->constrained('aparatos')->nullOnDelete();
            $table->string('imei_equipo')->nullable();
            $table->string('imei_sim')->nullable();
            $table->date('fecha_entrega_sim')->nullable();
            $table->date('fecha_renovacion_equipo')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lineas_telefonicas');
    }
};
