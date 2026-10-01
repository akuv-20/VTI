<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lineas_telefonicas', function (Blueprint $table) {
            // Tipo de chip de la línea: SIM física, eSIM o BAM (banda ancha móvil)
            $table->string('tipo_chip', 10)->nullable()->after('imei_sim');
        });
    }

    public function down(): void
    {
        Schema::table('lineas_telefonicas', function (Blueprint $table) {
            $table->dropColumn('tipo_chip');
        });
    }
};
