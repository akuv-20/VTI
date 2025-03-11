<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('companias', function (Blueprint $table) {
            $table->id(); // Campo autoincremental
            $table->string('nombre'); // Nombre de la compañía
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('companias');
    }
};