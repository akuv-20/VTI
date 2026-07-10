<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Raw SQL para no depender de doctrine/dbal
        DB::statement("ALTER TABLE `actas_entrega_telefono` MODIFY `condicion` VARCHAR(255) NULL");
        DB::statement("ALTER TABLE `actas_devolucion_telefono` MODIFY `condicion` VARCHAR(255) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `actas_entrega_telefono` MODIFY `condicion` VARCHAR(255) NOT NULL DEFAULT 'Nuevo'");
        DB::statement("ALTER TABLE `actas_devolucion_telefono` MODIFY `condicion` VARCHAR(255) NOT NULL DEFAULT 'Usado'");
    }
};
