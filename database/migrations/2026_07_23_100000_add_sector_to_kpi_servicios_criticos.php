<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_servicios_criticos', function (Blueprint $table) {
            // planta | campo | null (sin asignar). Nullable para poder asignarlo
            // después en los servicios ya importados.
            $table->string('sector')->nullable()->after('grupo');
            $table->index('sector');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_servicios_criticos', function (Blueprint $table) {
            $table->dropIndex(['sector']);
            $table->dropColumn('sector');
        });
    }
};
