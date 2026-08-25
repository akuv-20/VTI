<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Excepciones del indicador de antivirus.
 *
 * Hay equipos que nunca van a tener el antivirus corporativo y que no son un
 * problema: macOS, Linux, algunos servidores. Sin una forma de exceptuarlos, el
 * indicador arrastra ruido permanente y se deja de mirar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_excepciones', function (Blueprint $table) {
            $table->id();

            // null = aplica a todos los dominios
            $table->string('dominio', 40)->nullable();

            $table->string('campo', 40);      // sistema_operativo | nombre_equipo
            $table->string('operador', 20);   // contiene | empieza | igual
            $table->string('valor', 200);

            // Obligatorio a propósito: en seis meses nadie recuerda por qué se
            // exceptuó algo, y una excepción sin justificar no se puede auditar.
            $table->string('motivo', 300);

            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['dominio', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_excepciones');
    }
};
