<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linea_usuario_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_linea_telefonica')
                  ->constrained('lineas_telefonicas')
                  ->cascadeOnDelete();
            $table->foreignId('id_usuario_anterior')
                  ->nullable()
                  ->constrained('usuarios_telefonicos')
                  ->nullOnDelete();
            $table->foreignId('id_usuario_nuevo')
                  ->nullable()
                  ->constrained('usuarios_telefonicos')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linea_usuario_historial');
    }
};
