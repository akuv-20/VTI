<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ModulosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modulos = [
            [
                'nombre'         => 'facturacion',
                'label'          => 'Facturación',
                'descripcion'    => 'Facturas, servicios, familias, empresas, compañías y cuentas contables',
                'route_prefixes' => ['facturas', 'servicios', 'familias', 'empresas', 'companias', 'cuentas_contables'],
                'orden'          => 1,
            ],
            [
                'nombre'         => 'telefonia',
                'label'          => 'Telefonía',
                'descripcion'    => 'Líneas telefónicas, emisores, usuarios, ubicaciones, marcas, aparatos y centros de costo',
                'route_prefixes' => ['lineas_telefonicas', 'emisores', 'usuarios_telefonicos', 'ubicaciones', 'marcas', 'aparatos', 'centros_costo'],
                'orden'          => 3,
            ],
            [
                'nombre'         => 'importaciones_movistar',
                'label'          => 'Importaciones Movistar',
                'descripcion'    => 'Carga y validación de facturas Movistar',
                'route_prefixes' => ['importaciones_movistar'],
                'orden'          => 4,
            ],
            [
                'nombre'         => 'importaciones_entel',
                'label'          => 'Importaciones Entel',
                'descripcion'    => 'Carga y validación de facturas Entel',
                'route_prefixes' => ['importaciones_entel'],
                'orden'          => 5,
            ],
            [
                'nombre'         => 'informes',
                'label'          => 'Informes',
                'descripcion'    => 'Informes de consumo telefónico',
                'route_prefixes' => ['informes'],
                'orden'          => 6,
            ],
        ];

        foreach ($modulos as $m) {
            \App\Models\Modulo::updateOrCreate(
                ['nombre' => $m['nombre']],
                [
                    'label'          => $m['label'],
                    'descripcion'    => $m['descripcion'],
                    'route_prefixes' => $m['route_prefixes'],
                    'orden'          => $m['orden'],
                    'activo'         => true,
                ]
            );
        }
    }
}
