<?php

namespace App\Console\Commands;

use App\Services\ActividadBuzones;
use Illuminate\Console\Command;

/**
 * Deja el análisis de buzones en caché antes de que alguien lo pida.
 *
 * Sin esto, el primer usuario que abra la pantalla después de que venza la
 * caché espera unos 40 segundos mientras se bajan los reportes de Graph.
 */
class AnalizarBuzones extends Command
{
    protected $signature = 'buzones:analizar';

    protected $description = 'Recalcula la actividad de los buzones de Microsoft 365 y la deja en caché';

    public function handle(ActividadBuzones $uso): int
    {
        $this->info('Consultando Microsoft Graph…');
        $inicio = microtime(true);

        try {
            $uso->olvidarCache();
            $d = $uso->analizar();
        } catch (\Throwable $e) {
            $this->error('No se pudo completar: ' . $e->getMessage());
            return self::FAILURE;
        }

        $r = $d['resumen'];

        $this->info(sprintf('Listo en %.1f s.', microtime(true) - $inicio));
        $this->line(sprintf(
            '  %s buzones · %s sin uso (%s%%) · %s excluidos',
            number_format($r['total']),
            number_format($r['sin_uso']),
            $r['sin_uso_pct'],
            number_format($r['excluidos'])
        ));

        if ($d['nombresOcultos']) {
            $this->warn('  Los informes del tenant tienen los nombres ofuscados: el detalle por persona no sirve.');
        }

        return self::SUCCESS;
    }
}
