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
        Schema::table('lineas_telefonicas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_centro_costo')->nullable()->after('id_ubicacion');
            $table->foreign('id_centro_costo')->references('id')->on('centros_costo')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lineas_telefonicas', function (Blueprint $table) {
            $table->dropForeign(['id_centro_costo']);
            $table->dropColumn('id_centro_costo');
        });
    }
};
