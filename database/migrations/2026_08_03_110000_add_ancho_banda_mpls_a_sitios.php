<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los sitios pueden tener dos anchos de banda contratados: el de salida a
 * Internet y el del enlace MPLS. El campo que ya existía queda como Internet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sitios', function (Blueprint $table) {
            $table->string('ancho_banda_mpls', 60)->nullable()->after('ancho_banda');
        });
    }

    public function down(): void
    {
        Schema::table('sitios', function (Blueprint $table) {
            $table->dropColumn('ancho_banda_mpls');
        });
    }
};
