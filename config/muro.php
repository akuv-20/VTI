<?php

/*
|--------------------------------------------------------------------------
| Muro de monitoreo
|--------------------------------------------------------------------------
|
| Réplica del «Main Dashboard for all» de CheckMK, panel por panel y en la
| misma posición. La pantalla se arma desde esta tabla: para mover, agregar
| o quitar un panel no hace falta tocar la vista.
|
| Áreas (columnas del muro, de izquierda a derecha, y la franja de abajo):
|   izquierda · segmentos · centro · derecha · abajo_izq · abajo
|
| Tipos de panel:
|   tabla               hosts de una carpeta con Wa/Cr y métricas
|   lista               hosts de una carpeta con su estado
|   hexagono_hosts      conteo de hosts de una carpeta por estado
|   hexagono_servicios  conteo de servicios de los hosts de una carpeta
|   medidor             semicírculo con una métrica de un host (cpu | ram)
|   numero              una métrica grande (in_bps | out_bps)
|   grafico             gráfico histórico de un host, de una de estas clases:
|                         cpu          CPU total (user + privileged)
|                         ram          memoria usada
|                         ancho_banda  entrada y salida de la Interface 1
|                         perdida      pérdida de paquetes del servicio PING
|                       con el rango en minutos.
|   fila                agrupa paneles lado a lado
|   vacio               hueco, para respetar la distribución original
|
| Las carpetas son las de WATO en CheckMK e incluyen sus subcarpetas
| (/redes trae también /redes/sur, /redes/peru…). Una caída solo avisa si el
| host aparece en algún panel.
|
*/

$tabla = fn(string $titulo, string $carpeta, array $columnas = ['cpu', 'ram', 'disco', 'interfaz', 'uptime']) =>
    ['tipo' => 'tabla', 'titulo' => $titulo, 'carpeta' => $carpeta, 'columnas' => $columnas];

$grafico = fn(string $titulo, string $clase, string $host, int $minutos) =>
    ['tipo' => 'grafico', 'titulo' => $titulo, 'clase' => $clase, 'host' => $host, 'minutos' => $minutos];

$fila = fn(array $paneles) => ['tipo' => 'fila', 'paneles' => $paneles];

return [

    // Segundos entre consultas de la pantalla. El servicio cachea 10 s, así
    // que bajar de eso no trae datos más frescos.
    'intervalo' => 15,

    // Segundos que una caída nueva queda resaltada (latiendo y encima del
    // resto) antes de volver a su lugar en rojo.
    'resaltado' => 12,

    'areas' => [

        'izquierda' => [
            $tabla('Servers Inventory', '/server'),
            $tabla('Escritorio Remoto Chile', '/terminalcl_2'),
            $tabla('Escritorio Remoto Peru', '/terminal_pe_2'),
            $tabla('Escritorio Remoto Peru', '/terminal_ufpe'),
        ],

        'segmentos' => [
            ['tipo' => 'lista', 'titulo' => 'Segmentos Verfrut', 'carpeta' => '/redes'],
        ],

        'centro' => [
            $fila([
                ['tipo' => 'hexagono_hosts', 'titulo' => 'RED Planta Rapel', 'carpeta' => '/access_point/planta_rapel'],
                ['tipo' => 'hexagono_hosts', 'titulo' => 'RED ORO VERDE',    'carpeta' => '/access_point/oro_verde'],
            ]),
            $fila([
                ['tipo' => 'hexagono_servicios', 'titulo' => 'SSL Sitios Verfrut', 'carpeta' => '/ssl'],
                ['tipo' => 'vacio'],
            ]),
            $fila([
                ['tipo' => 'lista', 'titulo' => 'Dispositivos',          'carpeta' => '/dispositivos'],
                ['tipo' => 'lista', 'titulo' => 'Antenas Punto a Punto', 'carpeta' => '/enlaces_ptp'],
            ]),
            // Longaví no tiene host de red propio en CheckMK; se mide con el
            // NVR de portería. Cambiar aquí si el enlace es otro equipo.
            $grafico('Enlace Planta Longavi 4Hrs',        'perdida', 'NVR-Longavi_Porteria', 240),
            $grafico('Monitoreo Enlace Planta Oro Verde', 'perdida', '24.PLANTAOROVERDE',    240),
            $grafico('Monitoreo Enlace Planta Rapel',     'perdida', '3.PLANTARAPEL',        240),
        ],

        'derecha' => [
            $fila([
                ['tipo' => 'medidor', 'titulo' => 'CPU BD01',   'host' => 'VFSTBD01', 'metrica' => 'cpu'],
                ['tipo' => 'medidor', 'titulo' => '% RAM BD01', 'host' => 'VFSTBD01', 'metrica' => 'ram'],
                ['tipo' => 'medidor', 'titulo' => 'CPU BD02',   'host' => 'VFSTBD02', 'metrica' => 'cpu'],
                ['tipo' => 'medidor', 'titulo' => '% RAM BD02', 'host' => 'VFSTBD02', 'metrica' => 'ram'],
            ]),
            $fila([$grafico('VFSTBD01 - CPU - 30 Minutos', 'cpu', 'VFSTBD01', 30),  $grafico('VFSTBD02 - CPU - 30 Minutos', 'cpu', 'VFSTBD02', 30)]),
            $fila([$grafico('VFSTBD01 - CPU - 8 Horas',    'cpu', 'VFSTBD01', 480), $grafico('VFSTBD02 - CPU - 8 Horas',    'cpu', 'VFSTBD02', 480)]),
            $fila([$grafico('VFSTBD01 - RAM - 30 Minutos', 'ram', 'VFSTBD01', 30),  $grafico('VFSTBD02 - RAM - 30 Minutos', 'ram', 'VFSTBD02', 30)]),
            $fila([$grafico('VFSTBD01 - RAM - 8 Horas',    'ram', 'VFSTBD01', 480), $grafico('VFSTBD02 - RAM - 8 Horas',    'ram', 'VFSTBD02', 480)]),
            $fila([
                ['tipo' => 'numero', 'titulo' => 'Upload BW BD01',   'host' => 'VFSTBD01', 'metrica' => 'out_bps'],
                ['tipo' => 'numero', 'titulo' => 'Download BW BD01', 'host' => 'VFSTBD01', 'metrica' => 'in_bps'],
                ['tipo' => 'numero', 'titulo' => 'Upload BW BD02',   'host' => 'VFSTBD02', 'metrica' => 'out_bps'],
                ['tipo' => 'numero', 'titulo' => 'Download BW BD02', 'host' => 'VFSTBD02', 'metrica' => 'in_bps'],
            ]),
            $fila([$grafico('VFSTBD01 - Bandwidth', 'ancho_banda', 'VFSTBD01', 60), $grafico('VFSTBD02 - Bandwidth', 'ancho_banda', 'VFSTBD02', 60)]),
        ],

        'abajo_izq' => [
            $tabla('ESXi Servers', '/esxi', ['cpu', 'ram_gib', 'uptime']),
        ],

        'abajo' => [
            $fila([
                $grafico('Monitoreo PERU Enlace PARWIL',   'perdida', 'VFPE-PARWIL',  240),
                $grafico('Monitoreo PERU Enlace CLARO',    'perdida', 'VFPE-CLARO',   240),
                $grafico('Monitoreo PERU Enlace CHICLAYO', 'perdida', '200.CHICLAYO', 240),
            ]),
        ],
    ],
];
