<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wom_plantilla', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_linea_telefonica')
                  ->unique()
                  ->constrained('lineas_telefonicas')
                  ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wom_plantilla');
    }
};
