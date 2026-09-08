<?php

namespace App\Console\Commands;

use App\Services\ActividadBuzones;
use App\Services\GraphClient;
use App\Services\RegistroMfa;
use Illuminate\Console\Command;

/**
 * Rehace en el servidor todo lo que viene de Entra ID.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  Por qué existe:
 *
 *  Construir estos análisis toma más de un minuto. Si se hiciera cuando alguien
 *  abre la pantalla, esa persona espera. Corriendo cada 15 minutos desde el
 *  scheduler, la pantalla siempre encuentra la caché caliente y responde al
 *  instante, y el trabajo lento pasa donde nadie lo ve.
 *
 *  El orden importa: primero el directorio, que es lo caro y lo que ambos
 *  análisis comparten. Una vez en caché, buzones y MFA lo reutilizan en vez de
 *  recorrer los seis mil usuarios cada uno por su lado.
 *
 *  Qué tan fresco es cada dato, que no es lo mismo para todos:
 *
 *   - signInActivity y el registro de MFA cambian durante el día. Aquí los 15
 *     minutos rinden.
 *   - Los reportes de uso de correo llegan con varios días de atraso de parte de
 *     Microsoft —traen su propia «Report Refresh Date»— y solo cambian una vez
 *     al día. Se rehacen igual porque la clasificación de un buzón depende
 *     también del inicio de sesión, que sí se mueve: alguien que entra hoy deja
 *     de contar como «nunca activado» aunque el reporte siga siendo el de ayer.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class RefrescarEntra extends Command
{
    protected $signature = 'entra:refrescar {--silencioso : Solo informa si algo falla}';

    protected $description = 'Rehace en segundo plano los datos de Entra ID: directorio, buzones y MFA';

    public function handle(GraphClient $graph, ActividadBuzones $buzones, RegistroMfa $mfa): int
    {
        $callado = $this->option('silencioso');
        $inicio  = microtime(true);
        $fallos  = [];

        // El directorio primero: es lo que comparten los dos análisis y las
        // pantallas de Entra ID.
        $this->paso($callado, 'Directorio', function () use ($graph) {
            $graph->olvidarDirectorio();
            $n = $graph->directorio()->count();
            $graph->firmas();
            $graph->fichas();

            return "{$n} cuentas";
        }, $fallos);

        $this->paso($callado, 'Actividad de buzones', function () use ($buzones) {
            $buzones->olvidarCache();
            $r = $buzones->analizar()['resumen'];

            return sprintf('%s buzones, %s sin uso', number_format($r['total']), number_format($r['sin_uso']));
        }, $fallos);

        $this->paso($callado, 'Registro de MFA', function () use ($mfa) {
            $mfa->olvidarCache();
            $r = $mfa->analizar()['resumen'];

            return sprintf('%s cuentas, %s sin MFA', number_format($r['total']), number_format($r['sin_mfa']));
        }, $fallos);

        if ($fallos) {
            $this->error(sprintf('Terminó con %d fallo(s) en %.1f s.', count($fallos), microtime(true) - $inicio));

            return self::FAILURE;
        }

        if (!$callado) {
            $this->info(sprintf('Todo al día en %.1f s.', microtime(true) - $inicio));
        }

        return self::SUCCESS;
    }

    /**
     * Ejecuta un paso sin que su fallo arrastre a los demás.
     *
     * Si Graph rechaza una consulta, los otros análisis pueden seguir sirviendo:
     * es mejor tener dos de tres al día que ninguno.
     */
    private function paso(bool $callado, string $nombre, callable $tarea, array &$fallos): void
    {
        $t = microtime(true);

        try {
            $detalle = $tarea();

            if (!$callado) {
                $this->line(sprintf('  %-22s %6.1f s   %s', $nombre, microtime(true) - $t, $detalle));
            }
        } catch (\Throwable $e) {
            $fallos[] = $nombre;
            report($e);
            $this->error(sprintf('  %-22s falló: %s', $nombre, $e->getMessage()));
        }
    }
}
