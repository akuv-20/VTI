<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('servicios', function (Blueprint $table) {
            // Agregar el campo codigo_servicio como varchar(255) y nullable
            $table->string('codigo_servicio')->nullable()->after('concepto');
        });
    }

    public function down()
    {
        Schema::table('servicios', function (Blueprint $table) {
            // Revertir los cambios: eliminar el campo codigo_servicio
            $table->dropColumn('codigo_servicio');
        });
    }
};