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
|   ranking             podio del mes de los técnicos (tickets del GLPI de
|                       Unifrutti), que alterna entre los países de
|                       'paises' cada 'cada' segundos
|   servicios           estado de los servicios externos críticos declarados
|                       en 'servicios', más abajo (ver ServiciosCriticos)
|   servidor            tarjeta de un host: CPU y RAM (valor actual, 8 h con
|                       los últimos 30 min resaltados) y red; detalle al pulsar
|   enlaces             estado consolidado de los enlaces de 'enlaces', más
|                       abajo: franja de 4 h, latencia y detalle al pulsar
|   fila                agrupa paneles lado a lado ('crece' => true para que
|                       ocupe el alto libre de su columna)
|
|   mesa                mesa de ayuda del GLPI de Unifrutti: cola, movimiento
|                       del día y últimos tickets, con aviso de lo que cambia
|
| Cualquier panel acepta 'ancho' => n para ocupar n partes de su fila, y
| 'alto' => '13rem' para fijarle la altura.
|   vacio               hueco, para respetar la distribución original
|   libre               hueco marcado que ocupa el alto libre de su columna,
|                       reservado para un panel futuro ('titulo', 'nota')
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

    // Segundos entre consultas de la pantalla. El estado de los hosts se
    // cachea 4 s y las métricas 30 s (ver MuroCheckMk): bajar de 5 no trae
    // datos más frescos.
    'intervalo' => 5,

    // Segundos que una caída nueva queda resaltada (latiendo y encima del
    // resto) antes de volver a su lugar en rojo.
    'resaltado' => 12,

    // Servicios externos del panel «servicios», en el orden en que se muestran.
    // Fuentes: m365 (Service Health de Graph; necesita ServiceHealth.Read.All),
    // statuspage (summary.json público) y http (se pide la URL y se mide).
    'servicios' => [
        ['nombre' => 'Exchange',   'fuente' => 'm365', 'id' => 'Exchange'],
        ['nombre' => 'Teams',      'fuente' => 'm365', 'id' => 'microsoftteams'],
        ['nombre' => 'SII',        'fuente' => 'http', 'url' => 'https://www.sii.cl/'],
        ['nombre' => 'SII Clave',  'fuente' => 'http', 'url' => 'https://zeus.sii.cl/AUT2000/InicioAutenticacion/IngresoRutClave.html'],
        ['nombre' => 'Claude',     'fuente' => 'statuspage', 'url' => 'https://status.claude.com/api/v2/summary.json'],
        ['nombre' => 'ChatGPT',    'fuente' => 'statuspage', 'url' => 'https://status.openai.com/api/v2/summary.json'],
    ],

    // Enlaces del panel «enlaces»: un host de CheckMK con servicio PING cada
    // uno. El orden en pantalla lo decide su salud (el que falla sube).
    'enlaces' => [
        // Longaví no tiene host de red propio en CheckMK; se mide con el NVR
        // de portería. Cambiar aquí si el enlace es otro equipo.
        ['nombre' => 'Planta Longaví',   'host' => 'NVR-Longavi_Porteria'],
        ['nombre' => 'Planta Oro Verde', 'host' => '24.PLANTAOROVERDE'],
        ['nombre' => 'Planta Rapel',     'host' => '3.PLANTARAPEL'],
        ['nombre' => 'Perú · Parwil',    'host' => 'VFPE-PARWIL'],
        ['nombre' => 'Perú · Claro',     'host' => 'VFPE-CLARO'],
        ['nombre' => 'Perú · Chiclayo',  'host' => '200.CHICLAYO'],
    ],

    'areas' => [

        'izquierda' => [
            $tabla('Servers Inventory', '/server'),
            $tabla('Escritorio Remoto Chile', '/terminalcl_2'),
            $tabla('Escritorio Remoto Peru', '/terminal_pe_2'),
            $tabla('Escritorio Remoto Peru', '/terminal_ufpe'),
        ],

        'segmentos' => [
            ['tipo' => 'lista', 'titulo' => 'Segmentos Verfrut', 'carpeta' => '/redes'],
            // Ocupa el alto que deja libre la lista.
            ['tipo' => 'servicios', 'titulo' => 'Servicios críticos'],
        ],

        'centro' => [
            $fila([
                ['tipo' => 'hexagono_hosts', 'titulo' => 'RED Planta Rapel', 'carpeta' => '/access_point/planta_rapel'],
                ['tipo' => 'hexagono_hosts', 'titulo' => 'RED ORO VERDE',    'carpeta' => '/access_point/oro_verde'],
            ]),
            $fila([
                ['tipo' => 'hexagono_servicios', 'titulo' => 'SSL Sitios Verfrut',    'carpeta' => '/ssl'],
                ['tipo' => 'hexagono_hosts',     'titulo' => 'Antenas Punto a Punto', 'carpeta' => '/enlaces_ptp'],
            ]),
            // Donde iban los gráficos de enlaces. Reemplazar por el próximo panel.
            ['tipo' => 'libre', 'titulo' => 'Espacio libre'],
        ],

        'derecha' => [
            // Una tarjeta por servidor reemplaza los 18 paneles que tenían las
            // BD (medidores, gráficos de 30 min y 8 h, números y red).
            $fila([
                ['tipo' => 'servidor', 'titulo' => 'VFSTBD01', 'host' => 'VFSTBD01'],
                ['tipo' => 'servidor', 'titulo' => 'VFSTBD02', 'host' => 'VFSTBD02'],
            ]),
            // Mesa de ayuda (GLPI de Unifrutti): ocupa lo que liberó compactar las BD.
            ['tipo' => 'mesa', 'titulo' => 'Mesa de ayuda · GLPI'],
            // Un podio por país, lado a lado (con un solo país no rota).
            $fila([
                ['tipo' => 'ranking', 'titulo' => 'Ranking técnicos', 'paises' => ['chile'], 'alto' => '13rem'],
                ['tipo' => 'ranking', 'titulo' => 'Ranking técnicos', 'paises' => ['peru'],  'alto' => '13rem'],
            ]),
        ],

        'abajo_izq' => [
            $tabla('ESXi Servers', '/esxi', ['cpu', 'ram_gib', 'uptime']),
        ],

        'abajo' => [
            $fila([
                // Reemplaza los seis gráficos de pérdida que había (tres aquí y tres en el centro).
                ['tipo' => 'enlaces', 'titulo' => 'Monitoreo de enlaces'],
            ]),
        ],
    ],
];
