<?php

namespace App\Console\Commands;

use App\Services\RegistroMfa;
use Illuminate\Console\Command;

/** Deja el estado de MFA en caché para que la pantalla abra al instante. */
class AnalizarMfa extends Command
{
    protected $signature = 'mfa:analizar';

    protected $description = 'Recalcula el estado de MFA de Entra ID y lo deja en caché';

    public function handle(RegistroMfa $mfa): int
    {
        $inicio = microtime(true);

        try {
            $mfa->olvidarCache();
            $r = $mfa->analizar()['resumen'];
        } catch (\Throwable $e) {
            $this->error('No se pudo completar: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Listo en %.1f s.', microtime(true) - $inicio));
        $this->line(sprintf(
            '  %s cuentas · %s con MFA · %s sin MFA (%s%%) · %s admins sin MFA',
            number_format($r['total']),
            number_format($r['con_mfa']),
            number_format($r['sin_mfa']),
            $r['sin_mfa_pct'],
            number_format($r['admins_sin_mfa'])
        ));

        if ($r['admins_sin_mfa'] > 0) {
            $this->warn('  Hay cuentas administrativas sin segundo factor.');
        }

        return self::SUCCESS;
    }
}
