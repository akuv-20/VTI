<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado reversible para la lista de buzones excluidos.
 *
 * La lista se arma a mano, buzón por buzón, y reconstruirla cuesta caro: no hay
 * forma de deducir cuáles eran. Con borrado lógico, sacar un buzón de la lista
 * —o vaciarla entera por accidente— deja de ser definitivo y se puede deshacer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buzones_excluidos', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('buzones_excluidos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
