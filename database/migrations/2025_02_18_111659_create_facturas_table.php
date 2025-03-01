<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id(); // Campo autoincremental
            $table->string('factura'); // Número de factura
            $table->double('valor_neto', 8, 2); // Valor neto
            $table->double('valor_iva', 8, 2); // Valor IVA
            $table->date('fecha_emision'); // Fecha de emisión
            $table->foreignId('id_servicio')->constrained('servicios')->onDelete('cascade'); // Relación con servicios
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('facturas');
    }
};