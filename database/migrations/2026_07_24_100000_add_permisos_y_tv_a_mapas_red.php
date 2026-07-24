<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Técnicos asignados a cada mapa (pueden mantener su contenido).
        Schema::create('mapa_red_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapa_id')->constrained('mapas_red')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mapa_id', 'user_id']);
        });

        Schema::table('mapas_red', function (Blueprint $table) {
            // Visible en solo lectura para todo usuario del módulo (ej: mapa General).
            $table->boolean('publico_lectura')->default(false)->after('en_tv');
            // URL TV propia del mapa, revocable individualmente.
            $table->string('tv_token', 64)->nullable()->unique()->after('publico_lectura');
        });

        // Backfill: token TV propio para los mapas existentes.
        foreach (DB::table('mapas_red')->pluck('id') as $id) {
            DB::table('mapas_red')->where('id', $id)->update(['tv_token' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mapa_red_user');
        Schema::table('mapas_red', function (Blueprint $table) {
            $table->dropColumn(['publico_lectura', 'tv_token']);
        });
    }
};
