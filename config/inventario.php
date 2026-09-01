<?php

/*
|--------------------------------------------------------------------------
| Dominios del módulo de Inventario
|--------------------------------------------------------------------------
|
| Cada dominio es un par GLPI + Active Directory que se administra por
| separado pero con las mismas pantallas. Todo lo que distingue a uno de
| otro vive acá: el resto del módulo se parametriza contra esta tabla.
|
| Las CREDENCIALES no están aquí — las conexiones (`glpi`, `glpi_unifrutti`,
| `default`, `tertiary`) se configuran en Admin → Configuración y se aplican
| en AppServiceProvider::boot(). Acá solo se dice cuál usa cada dominio.
|
| Para sumar un tercer dominio: agregar su entrada, su conexión GLPI en
| config/database.php, su conexión LDAP en el provider, y su fila en la
| tabla `modulos`. Ninguna pantalla debería necesitar cambios.
|
*/

return [

    'dominios' => [

        'verfrut' => [
            'label'     => 'Verfrut',
            'dominio'   => 'verfrut.cl',
            'glpi'      => 'glpi',        // conexión de base de datos
            'ad'        => null,          // null = conexión LDAP por defecto
            'gate'      => 'acceso_inventario_verfrut',
            'color'     => '#2563eb',
            'icono'     => 'bi-building',

            // Antivirus corporativo del dominio. Se busca por coincidencia
            // parcial sobre el nombre que reporta el agente, porque cada
            // producto se registra con variantes ("Endpoint Security",
            // "Endpoint Security Tools Antimalware"…).
            //
            // Puede ser una lista: Verfrut está migrando de Bitdefender a ESET,
            // así que durante la transición un equipo con cualquiera de los dos
            // cuenta como protegido.
            'antivirus' => ['Bitdefender', 'ESET'],

            // Usuario de GLPI que no cuenta como equipo asignado. Es un id
            // propio de ESTA base: en la de Unifrutti el mismo número es otra
            // persona, por eso va por dominio y no como constante global.
            'excluir_user' => 138,
        ],

        'unifrutti' => [
            'label'     => 'Unifrutti',
            'dominio'   => 'unifrutti.com',
            'glpi'      => 'glpi_unifrutti',
            'ad'        => 'tertiary',
            'gate'      => 'acceso_inventario_unifrutti',
            'color'     => '#0078d4',
            'icono'     => 'bi-globe-americas',
            'antivirus' => 'ESET',
            'excluir_user' => null,
        ],

    ],

];
