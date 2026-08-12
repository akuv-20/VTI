<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `vpn` pasa a admitir «sin responder».
 *
 * Era `tinyint NOT NULL default 0`, alimentado por un checkbox: una casilla sin
 * marcar y un «no tiene VPN al datacenter» llegaban idénticos a la base. De 15
 * fichas, 14 estaban en 0 sin que eso signifique que alguien lo haya mirado.
 *
 * Para el informe hace falta la diferencia, así que la columna se hace nullable
 * y esos 0 —que vienen del valor por defecto, no de una respuesta— pasan a NULL.
 * El único sitio con 1 se conserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sitios', function (Blueprint $table) {
            $table->boolean('vpn')->nullable()->default(null)->change();
        });

        // Los 0 existentes son el default del checkbox, no una respuesta.
        DB::table('sitios')->where('vpn', 0)->update(['vpn' => null]);
    }

    public function down(): void
    {
        DB::table('sitios')->whereNull('vpn')->update(['vpn' => 0]);

        Schema::table('sitios', function (Blueprint $table) {
            $table->boolean('vpn')->default(false)->nullable(false)->change();
        });
    }
};
