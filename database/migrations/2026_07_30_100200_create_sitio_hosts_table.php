<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un sitio puede tener varios hosts en CheckMK: el enlace principal, el
        // túnel VPN y eventualmente un respaldo (ej: 13.QUILAMUTA + 37.QUILAMUTA_VPN).
        Schema::create('sitio_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitio_id')->constrained('sitios')->cascadeOnDelete();
            $table->string('host_name');
            $table->string('rol', 20)->default('enlace'); // enlace | vpn | respaldo | otro
            $table->timestamps();

            $table->unique(['sitio_id', 'host_name']);
            $table->index('host_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitio_hosts');
    }
};
