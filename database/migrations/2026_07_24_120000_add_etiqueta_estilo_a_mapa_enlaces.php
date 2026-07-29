<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapa_enlaces', function (Blueprint $table) {
            $table->unsignedSmallInteger('etiqueta_px')->default(12)->after('etiqueta'); // 8-40 px
            $table->string('etiqueta_color', 20)->default('#334155')->after('etiqueta_px');
        });
    }

    public function down(): void
    {
        Schema::table('mapa_enlaces', function (Blueprint $table) {
            $table->dropColumn(['etiqueta_px', 'etiqueta_color']);
        });
    }
};
