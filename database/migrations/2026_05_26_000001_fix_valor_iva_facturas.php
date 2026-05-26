<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El formulario original calculaba valor_iva = valor_neto * 1.19 (el total)
     * en lugar de valor_neto * 0.19 (solo el IVA).
     * Esta migración corrige todos los registros afectados.
     */
    public function up(): void
    {
        // Detectar registros con el bug: valor_iva ≈ valor_neto * 1.19
        // Condición segura: si valor_iva > valor_neto, fue guardado como total, no como IVA
        DB::statement('
            UPDATE facturas
            SET valor_iva = ROUND(valor_neto * 0.19)
            WHERE valor_iva > valor_neto
        ');
    }

    public function down(): void
    {
        // No hay forma segura de revertir sin backup; no hacer nada
    }
};
