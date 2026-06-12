<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('actas_entrega_equipo', function (Blueprint $table) {
            $table->string('procesador')->nullable()->after('sistema_operativo');
            $table->string('ram')->nullable()->after('procesador');
            $table->string('disco')->nullable()->after('ram');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actas_entrega_equipo', function (Blueprint $table) {
            $table->dropColumn(['procesador', 'ram', 'disco']);
        });
    }
};
