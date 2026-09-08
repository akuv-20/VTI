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
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_aparato')->nullable()->constrained('aparatos')->nullOnDelete();
            $table->string('imei')->nullable();               // identificación del equipo
            $table->string('propiedad')->default('Empresa');  // Empresa | Personal
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios_telefonicos')->nullOnDelete();
            $table->foreignId('id_ubicacion')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->string('estado')->default('En uso');      // En uso | En bodega | De baja
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['propiedad', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
