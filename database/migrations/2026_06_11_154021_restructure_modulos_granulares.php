<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('modulos', 'grupo')) {
            Schema::table('modulos', function (Blueprint $table) {
                $table->string('grupo', 50)->nullable()->after('label');
            });
        }

        // ── Módulos granulares: [grupo, nombre, label, descripción, prefixes, orden]
        $nuevos = [
            ['Facturación', 'facturas',           'Facturas',             'Facturas, pendientes y resúmenes anuales',        ['facturas.'],           1],
            ['Facturación', 'entregas_facturas',  'Entregas de Facturas', 'Registro e impresión de entregas de facturas',    ['entregas_facturas.'],  2],
            ['Facturación', 'servicios',          'Servicios',            'Mantenedor de servicios',                         ['servicios.'],          3],
            ['Facturación', 'familias',           'Familias',             'Mantenedor de familias',                          ['familias.'],           4],
            ['Facturación', 'empresas',           'Empresas',             'Mantenedor de empresas',                          ['empresas.'],           5],
            ['Facturación', 'companias',          'Compañías',            'Mantenedor de compañías',                         ['companias.'],          6],
            ['Facturación', 'cuentas_contables',  'Cuentas Contables',    'Mantenedor de cuentas contables',                 ['cuentas_contables.'],  7],

            ['Telefonía', 'lineas_telefonicas',     'Líneas Telefónicas',     'Gestión de líneas telefónicas',                  ['lineas_telefonicas.'],     10],
            ['Telefonía', 'emisores',               'Emisores',               'Mantenedor de emisores',                         ['emisores.'],               11],
            ['Telefonía', 'usuarios_telefonicos',   'Usuarios Telefónicos',   'Mantenedor de usuarios telefónicos',             ['usuarios_telefonicos.'],   12],
            ['Telefonía', 'ubicaciones',            'Ubicaciones',            'Mantenedor de ubicaciones',                      ['ubicaciones.'],            13],
            ['Telefonía', 'marcas',                 'Marcas',                 'Mantenedor de marcas',                           ['marcas.'],                 14],
            ['Telefonía', 'aparatos',               'Aparatos',               'Mantenedor de aparatos telefónicos',             ['aparatos.'],               15],
            ['Telefonía', 'centros_costo',          'Centros de Costo',       'Mantenedor de centros de costo',                 ['centros_costo.'],          16],
            ['Telefonía', 'importaciones_movistar', 'Importaciones Movistar', 'Carga y validación de facturas Movistar',        ['importaciones_movistar.'], 17],
            ['Telefonía', 'importaciones_entel',    'Importaciones Entel',    'Carga y validación de facturas Entel',           ['importaciones_entel.'],    18],
            ['Telefonía', 'importaciones_wom',      'Importaciones WOM',      'Ingreso manual de facturación WOM y plantilla',  ['importaciones_wom.'],      19],
            ['Telefonía', 'informes',               'Informe Telefonía',      'Informes de consumo telefónico',                 ['informes.'],               20],
            ['Telefonía', 'actas_entrega_telefono', 'Actas de Entrega',       'Actas de entrega de teléfonos',                  ['actas_entrega_telefono.'], 21],

            ['Inventario TI', 'inventario_ti', 'Inventario TI', 'Equipos GLPI, actas de entrega y dashboard de salud', ['inventario_ti.'], 30],

            ['Active Directory', 'active_directory',  'AD Verfrut',              'Gestión de usuarios del AD corporativo Verfrut',  ['admin.active_directory.'],  40],
            ['Active Directory', 'active_directory2', 'AD Grupo Verfrut (Perú)', 'Gestión de usuarios del AD Grupo Verfrut Perú',   ['admin.active_directory2.'], 41],
        ];

        foreach ($nuevos as [$grupo, $nombre, $label, $desc, $prefixes, $orden]) {
            DB::table('modulos')->updateOrInsert(
                ['nombre' => $nombre],
                [
                    'grupo'          => $grupo,
                    'label'          => $label,
                    'descripcion'    => $desc,
                    'route_prefixes' => json_encode($prefixes),
                    'orden'          => $orden,
                    'activo'         => true,
                    'updated_at'     => now(),
                    'created_at'     => now(),
                ]
            );
        }

        // ── Migrar asignaciones de los módulos compuestos antiguos ──────────
        $mapa = [
            'mantenedores' => ['servicios', 'familias', 'empresas', 'companias', 'cuentas_contables'],
            'facturacion'  => ['facturas', 'servicios', 'familias', 'empresas', 'companias', 'cuentas_contables'],
            'telefonia'    => ['lineas_telefonicas', 'emisores', 'usuarios_telefonicos', 'ubicaciones',
                               'marcas', 'aparatos', 'centros_costo'],
        ];

        foreach ($mapa as $viejoNombre => $nuevosNombres) {
            $viejo = DB::table('modulos')->where('nombre', $viejoNombre)->first();
            if (!$viejo) continue;

            $userIds  = DB::table('modulo_user')->where('modulo_id', $viejo->id)->pluck('user_id');
            $nuevosIds = DB::table('modulos')->whereIn('nombre', $nuevosNombres)->pluck('id');

            foreach ($userIds as $userId) {
                foreach ($nuevosIds as $nuevoId) {
                    DB::table('modulo_user')->insertOrIgnore([
                        'modulo_id' => $nuevoId,
                        'user_id'   => $userId,
                    ]);
                }
            }

            // Eliminar módulo compuesto y sus asignaciones
            DB::table('modulo_user')->where('modulo_id', $viejo->id)->delete();
            DB::table('modulos')->where('id', $viejo->id)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            $table->dropColumn('grupo');
        });
    }
};
