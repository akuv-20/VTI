<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapas_red', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');                          // "General", "Planta Rapel", …
            $table->string('descripcion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0); // orden en listado y rotación TV
            $table->boolean('activo')->default(true);
            $table->boolean('en_tv')->default(true);           // participa en la rotación del modo TV
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapas_red');
    }
};
