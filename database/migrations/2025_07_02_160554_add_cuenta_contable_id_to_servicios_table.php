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
            // Añadir la columna id_cuenta_contable
            $table->foreignId('id_cuenta_contable')
                  ->nullable() // Permitir valores nulos temporalmente si ya tienes servicios existentes sin cuenta
                  ->after('id_compania') // O después de cualquier otra columna que consideres lógica
                  ->constrained('cuentas_contables') // Establecer la clave foránea a la tabla cuentas_contables
                  ->onDelete('set null'); // Si una cuenta contable se elimina, establecer id_cuenta_contable a null en servicios

            // Si no quieres que sea nullable, puedes quitar ->nullable() y ->onDelete('set null')
            // y asegurarte de que todos los servicios existentes tengan una cuenta contable válida antes de ejecutar la migración.
            // Ejemplo si no es nullable:
            // $table->foreignId('id_cuenta_contable')->after('id_compania')->constrained('cuentas_contables')->onDelete('cascade');
            // (onDelete('cascade') eliminaría los servicios si se elimina la cuenta contable, lo cual no suele ser deseable)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            // Eliminar la clave foránea primero
            $table->dropConstrainedForeignId('id_cuenta_contable');
            // Luego eliminar la columna
            $table->dropColumn('id_cuenta_contable');
        });
    }
};