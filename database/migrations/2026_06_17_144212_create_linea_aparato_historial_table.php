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
        Schema::create('linea_aparato_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_linea_telefonica')
                  ->constrained('lineas_telefonicas')
                  ->cascadeOnDelete();
            $table->foreignId('id_aparato_anterior')
                  ->nullable()
                  ->constrained('aparatos')
                  ->nullOnDelete();
            $table->foreignId('id_aparato_nuevo')
                  ->nullable()
                  ->constrained('aparatos')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('linea_aparato_historial');
    }
};
