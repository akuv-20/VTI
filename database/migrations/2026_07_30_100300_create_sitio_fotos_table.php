<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Galería polimórfica: sirve tanto a un sitio como a un equipo de red.
        Schema::create('sitio_fotos', function (Blueprint $table) {
            $table->id();
            $table->morphs('fotable');                     // Sitio | SitioEquipo
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('categoria', 30)->nullable();   // rack | antena | gabinete | entorno | tablero | diagrama | otro
            $table->string('titulo')->nullable();
            $table->boolean('portada')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->foreignId('subida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitio_fotos');
    }
};
