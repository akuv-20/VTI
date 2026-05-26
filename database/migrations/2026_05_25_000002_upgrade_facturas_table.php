<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Tipo de factura
            $table->enum('tipo', ['Mensual', 'Esporádica'])->default('Mensual')->after('id');

            // Proveedor (para esporádicas sin servicio definido)
            $table->string('proveedor')->nullable()->after('tipo');

            // Cuenta contable directa (para esporádicas; periódicas la heredan del servicio)
            $table->foreignId('id_cuenta_contable')
                  ->nullable()
                  ->after('proveedor')
                  ->constrained('cuentas_contables')
                  ->nullOnDelete();

            // Hacer id_servicio nullable
            $table->foreignId('id_servicio')->nullable()->change();
        });

        // Marcar todas las facturas existentes como Mensual (ya lo son por defecto)
        DB::table('facturas')->update(['tipo' => 'Mensual']);
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropForeign(['id_cuenta_contable']);
            $table->dropColumn(['tipo', 'proveedor', 'id_cuenta_contable']);
            $table->foreignId('id_servicio')->nullable(false)->change();
        });
    }
};
