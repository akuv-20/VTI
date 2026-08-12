<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zonas: agrupación propia de los sitios, por encima de comuna y región.
 *
 * No se deriva de la división política porque no coincide con ella: una zona
 * de operación puede cruzar comunas, y dos campos de la misma comuna pueden
 * pertenecer a zonas distintas. Por eso es un mantenedor y no una constante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();

            // Orden manual: las zonas tienen un orden geográfico (norte a sur)
            // que no es el alfabético. Empate resuelto por nombre.
            $table->unsignedSmallInteger('orden')->default(0);

            $table->timestamps();
        });

        Schema::table('sitios', function (Blueprint $table) {
            // nullOnDelete y no cascade: borrar una zona jamás debe llevarse por
            // delante las fichas de los sitios que la usaban.
            $table->foreignId('zona_id')->nullable()->after('empresa_id')
                ->constrained('zonas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sitios', function (Blueprint $table) {
            $table->dropForeign(['zona_id']);
            $table->dropColumn('zona_id');
        });

        Schema::dropIfExists('zonas');
    }
};
