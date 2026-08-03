<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes a la ficha de sitio:
 *  - Empresa e ISP dejan de ser texto libre y apuntan a los mantenedores existentes.
 *  - La dirección escrita se reemplaza por el link de Google Maps.
 *  - Del UPS interesa la capacidad (kVA), no la autonomía estimada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sitios', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('estado_enlace')
                ->constrained('empresas')->nullOnDelete();
            $table->text('maps_url')->nullable()->after('comuna');
            $table->foreignId('isp_id')->nullable()->after('enlace_tipo')
                ->constrained('companias')->nullOnDelete();
            $table->decimal('ups_kva', 6, 2)->nullable()->after('ups_modelo');
        });

        Schema::table('sitios', function (Blueprint $table) {
            $table->dropColumn(['empresa', 'direccion', 'isp', 'ups_autonomia_min']);
        });
    }

    public function down(): void
    {
        Schema::table('sitios', function (Blueprint $table) {
            $table->string('empresa')->nullable()->after('estado_enlace');
            $table->string('direccion')->nullable()->after('comuna');
            $table->string('isp')->nullable()->after('enlace_tipo');
            $table->unsignedSmallInteger('ups_autonomia_min')->nullable()->after('ups_modelo');
        });

        Schema::table('sitios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
            $table->dropConstrainedForeignId('isp_id');
            $table->dropColumn(['maps_url', 'ups_kva']);
        });
    }
};
