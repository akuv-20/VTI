<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\DhcpReserva;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DhcpRevisarInactivas extends Command
{
    protected $signature = 'dhcp:revisar-inactivas {--dias= : Umbral en días (sobrescribe el configurado)}';
    protected $description = 'Detecta reservas DHCP sin actividad sobre el umbral y notifica si hay destinatario configurado';

    public function handle(): int
    {
        $umbral = (int) ($this->option('dias') ?: Configuracion::get('dhcp_umbral_dias') ?: 90);
        $limite = now()->subDays($umbral);

        $inactivas = DhcpReserva::with('scope')
            ->where('activa', true)
            ->where(function ($q) use ($limite) {
                $q->where('ultima_actividad', '<', $limite)->orWhereNull('ultima_actividad');
            })
            ->orderByRaw('ultima_actividad IS NULL DESC')
            ->orderBy('ultima_actividad', 'asc')
            ->get();

        $total = $inactivas->count();
        $this->info("Reservas DHCP sin actividad hace más de {$umbral} días: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        // Log siempre (queda registro aunque no haya correo)
        Log::warning("DHCP: {$total} reserva(s) inactivas > {$umbral} días", [
            'ips' => $inactivas->take(50)->pluck('ip')->all(),
        ]);

        foreach ($inactivas->take(20) as $r) {
            $dias = $r->ultima_actividad ? (int) $r->ultima_actividad->diffInDays(now()) : null;
            $this->line(sprintf('  %-16s %-22s %s',
                $r->ip,
                $r->nombre ?: ($r->mac ?: '—'),
                $dias === null ? 'nunca vista activa' : "{$dias} días"
            ));
        }

        // Correo opcional
        $destino = Configuracion::get('dhcp_alerta_email');
        if ($destino) {
            try {
                $cuerpo = "Reservas DHCP sin actividad hace más de {$umbral} días: {$total}\n\n";
                foreach ($inactivas as $r) {
                    $dias = $r->ultima_actividad ? (int) $r->ultima_actividad->diffInDays(now()) . ' días' : 'nunca vista activa';
                    $cuerpo .= sprintf("%-16s  %-24s  %-14s  %s\n",
                        $r->ip, $r->nombre ?: '—', $r->scope->nombre ?? $r->scope_id, $dias);
                }

                Mail::raw($cuerpo, function ($m) use ($destino, $total) {
                    $m->to($destino)->subject("DHCP · {$total} reservas inactivas para depurar");
                });
                $this->info("Correo de alerta enviado a {$destino}.");
            } catch (\Throwable $e) {
                $this->warn('No se pudo enviar el correo: ' . $e->getMessage());
                Log::error('DHCP alerta correo falló: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
