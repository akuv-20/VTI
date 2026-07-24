<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_disponibilidad_mensual', function (Blueprint $table) {
            // Segundos de caída que caen dentro de excepciones justificadas y
            // por tanto se excluyen del denominador del KPI.
            $table->unsignedBigInteger('excepcion_seconds')->default(0)->after('downtime_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_disponibilidad_mensual', function (Blueprint $table) {
            $table->dropColumn('excepcion_seconds');
        });
    }
};
