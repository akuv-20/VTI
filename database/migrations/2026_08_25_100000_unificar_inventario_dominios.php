<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica Inventario TI e Inventario Unifrutti en un solo módulo con selector
 * de dominio.
 *
 * Dos cambios:
 *
 * 1. `actas_entrega_equipo` gana la columna `dominio`. Sin ella, al compartir
 *    tabla entre dos GLPI el `glpi_computer_id` deja de ser único —el equipo
 *    45 de Verfrut y el 45 de Unifrutti serían el mismo— y el historial de una
 *    ficha mostraría actas del otro dominio. Todo lo existente es de Verfrut,
 *    que hasta ahora era el único que emitía actas.
 *
 * 2. Los módulos pasan a reconocer las rutas nuevas (`inventario.verfrut.*`,
 *    `inventario.unifrutti.*`) conservando las viejas, que quedan como
 *    redirecciones. Así nadie pierde el acceso durante el despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actas_entrega_equipo', function (Blueprint $table) {
            $table->string('dominio', 40)->default('verfrut')->after('id');
            $table->index(['dominio', 'glpi_computer_id']);
        });

        // Las actas que ya existen son todas del GLPI de Verfrut.
        DB::table('actas_entrega_equipo')->update(['dominio' => 'verfrut']);

        $this->prefijos('inventario_ti', ['inventario.verfrut.', 'inventario_ti.']);
        $this->prefijos('inventario_unifrutti', ['inventario.unifrutti.', 'admin.inventario_unifrutti.']);

        // El módulo de Unifrutti deja de ser "el otro inventario" y pasa a ser
        // el permiso del dominio Unifrutti dentro del módulo unificado.
        DB::table('modulos')->where('nombre', 'inventario_unifrutti')->update([
            'grupo'       => 'Inventario TI',
            'label'       => 'Inventario Unifrutti',
            'descripcion' => 'Acceso al dominio unifrutti.com: equipos, dashboard, cruce con AD y actas',
            'orden'       => 31,
            'updated_at'  => now(),
        ]);

        DB::table('modulos')->where('nombre', 'inventario_ti')->update([
            'label'       => 'Inventario Verfrut',
            'descripcion' => 'Acceso al dominio verfrut.cl: equipos, dashboard, cruce con AD y actas',
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('actas_entrega_equipo', function (Blueprint $table) {
            $table->dropIndex(['dominio', 'glpi_computer_id']);
            $table->dropColumn('dominio');
        });

        $this->prefijos('inventario_ti', ['inventario_ti.']);
        $this->prefijos('inventario_unifrutti', ['admin.inventario_unifrutti.']);

        DB::table('modulos')->where('nombre', 'inventario_unifrutti')->update([
            'grupo'       => 'Inventario Unifrutti',
            'label'       => 'Cruce AD ↔ GLPI',
            'descripcion' => 'Cruce de equipos del AD Unifrutti contra el inventario del GLPI Helpdesk',
            'orden'       => 43,
            'updated_at'  => now(),
        ]);

        DB::table('modulos')->where('nombre', 'inventario_ti')->update([
            'label'       => 'Inventario TI',
            'descripcion' => 'Equipos GLPI, actas de entrega y dashboard de salud',
            'updated_at'  => now(),
        ]);
    }

    private function prefijos(string $nombre, array $prefijos): void
    {
        DB::table('modulos')->where('nombre', $nombre)->update([
            'route_prefixes' => json_encode($prefijos),
            'updated_at'     => now(),
        ]);
    }
};
