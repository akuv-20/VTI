<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id(); // Campo autoincremental
            $table->string('familia'); // Familia del servicio
            $table->string('empresa'); // Empresa asociada
            $table->string('compania'); // Compañía asociada
            $table->string('servicio'); // Nombre del servicio
            $table->string('fecha_facturacion'); // Fecha de facturación
            $table->string('concepto'); // Concepto del servicio
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('servicios');
    }
};