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
        Schema::table('servicios', function (Blueprint $table) {
            // Añadir la columna 'es_periodico' de tipo booleano
            // default(false) significa que por defecto no es periódico
            $table->boolean('es_periodico')->default(false)->after('concepto'); // Puedes ajustar 'after' según tu preferencia
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            // Eliminar la columna 'es_periodico' si se revierte la migración
            $table->dropColumn('es_periodico');
        });
    }
};