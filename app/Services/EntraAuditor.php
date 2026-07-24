<?php

namespace App\Services;

use App\Models\EntraRegla;
use Illuminate\Support\Collection;

/**
 * Evalúa las reglas configurables de calidad de datos sobre la colección
 * de usuarios traída desde Microsoft Graph.
 *
 * Cada regla devuelve un resultado uniforme:
 *   evaluadas / ok / fallos / score / hallazgos
 *
 * "hallazgos" es la lista de cuentas que incumplen, ya lista para mostrar.
 */
class EntraAuditor
{
    /** @var Collection<int,array> */
    private Collection $usuarios;

    /** Indica si la colección trae datos de signInActivity. */
    private bool $tieneSignIn;

    public function __construct(Collection $usuarios, bool $tieneSignIn = false)
    {
        $this->usuarios    = $usuarios;
        $this->tieneSignIn = $tieneSignIn;
    }

    /**
     * Evalúa todas las reglas indicadas.
     *
     * @param  Collection<int,EntraRegla>  $reglas
     * @return array<int,array>  resultado por regla, indexado por id de regla
     */
    public function evaluar(Collection $reglas): array
    {
        $resultados = [];

        foreach ($reglas as $regla) {
            $resultados[$regla->id] = $this->evaluarRegla($regla);
        }

        return $resultados;
    }

    public function evaluarRegla(EntraRegla $regla): array
    {
        // Universo de cuentas sobre el que aplica la regla
        $universo = $regla->solo_habilitados
            ? $this->usuarios->filter(fn($u) => ($u['accountEnabled'] ?? true) !== false)
            : $this->usuarios;

        // Regla que necesita datos de inicio de sesión sin permiso disponible
        if ($regla->requiere_sign_in && !$this->tieneSignIn) {
            return $this->resultadoNoDisponible(
                $regla,
                'Requiere el permiso AuditLog.Read.All en la aplicación de Azure.'
            );
        }

        $hallazgos = match ($regla->tipo) {
            'valores_permitidos'  => $this->chequearValoresPermitidos($regla, $universo),
            'obligatorio'         => $this->chequearObligatorio($regla, $universo),
            'formato_consistente' => $this->chequearFormatoConsistente($regla, $universo),
            'sin_duplicados'      => $this->chequearSinDuplicados($regla, $universo),
            'actividad_reciente'  => $this->chequearActividadReciente($regla, $universo),
            default               => collect(),
        };

        $evaluadas = $universo->count();
        $fallos    = $hallazgos->count();
        $ok        = max(0, $evaluadas - $fallos);

        return [
            'regla'        => $regla,
            'disponible'   => true,
            'motivo'       => null,
            'evaluadas'    => $evaluadas,
            'ok'           => $ok,
            'fallos'       => $fallos,
            'score'        => $evaluadas > 0 ? round($ok / $evaluadas * 100, 1) : 100.0,
            'hallazgos'    => $hallazgos,
        ];
    }

    private function resultadoNoDisponible(EntraRegla $regla, string $motivo): array
    {
        return [
            'regla'      => $regla,
            'disponible' => false,
            'motivo'     => $motivo,
            'evaluadas'  => 0,
            'ok'         => 0,
            'fallos'     => 0,
            'score'      => null,
            'hallazgos'  => collect(),
        ];
    }

    /* ── Tipos de regla ──────────────────────────────────────────────────── */

    /** El valor debe estar dentro de la lista permitida. Los vacíos no cuentan aquí. */
    private function chequearValoresPermitidos(EntraRegla $regla, Collection $universo): Collection
    {
        $permitidos = $regla->config['valores'] ?? [];
        if (empty($permitidos)) return collect();

        // Comparación tolerante a mayúsculas para no duplicar con formato_consistente
        $normalizados = array_map(fn($v) => $this->normalizar($v), $permitidos);

        return $universo
            ->filter(function ($u) use ($regla, $normalizados) {
                $valor = trim((string) ($u[$regla->campo] ?? ''));
                if ($valor === '') return false; // vacío → lo cubre la regla "obligatorio"
                return !in_array($this->normalizar($valor), $normalizados, true);
            })
            ->map(fn($u) => $this->hallazgo($u, [
                'detalle' => (string) ($u[$regla->campo] ?? ''),
                'motivo'  => 'Valor fuera de la lista permitida',
            ]))
            ->values();
    }

    /** El campo no puede estar vacío. */
    private function chequearObligatorio(EntraRegla $regla, Collection $universo): Collection
    {
        return $universo
            ->filter(fn($u) => trim((string) ($u[$regla->campo] ?? '')) === '')
            ->map(fn($u) => $this->hallazgo($u, [
                'detalle' => null,
                'motivo'  => 'Campo vacío',
            ]))
            ->values();
    }

    /**
     * Detecta variantes de escritura del mismo valor.
     * Agrupa por valor normalizado; dentro de cada grupo la variante más
     * frecuente se toma como canónica y el resto se marca como hallazgo.
     */
    private function chequearFormatoConsistente(EntraRegla $regla, Collection $universo): Collection
    {
        $conValor = $universo->filter(fn($u) => trim((string) ($u[$regla->campo] ?? '')) !== '');

        $hallazgos = collect();

        $conValor
            ->groupBy(fn($u) => $this->normalizar($u[$regla->campo]))
            ->each(function (Collection $grupo) use ($regla, &$hallazgos) {
                // Variantes literales dentro del grupo normalizado
                $variantes = $grupo->groupBy(fn($u) => trim((string) $u[$regla->campo]));
                if ($variantes->count() < 2) return; // escritura uniforme

                // La variante más usada se considera la correcta.
                // Si hay empate, gana la mejor escrita (ni todo mayúsculas ni todo minúsculas).
                $canonica = $variantes
                    ->map(fn(Collection $g, string $v) => [
                        'variante' => $v,
                        'count'    => $g->count(),
                        'calidad'  => $this->calidadEscritura($v),
                    ])
                    ->sortByDesc(fn($a) => [$a['count'], $a['calidad']])
                    ->first()['variante'];

                $variantes->each(function (Collection $cuentas, string $variante) use ($canonica, $regla, &$hallazgos) {
                    if ($variante === $canonica) return;
                    foreach ($cuentas as $u) {
                        $hallazgos->push($this->hallazgo($u, [
                            'detalle' => $variante,
                            'motivo'  => 'Debería escribirse "' . $canonica . '"',
                            'sugerido'=> $canonica,
                        ]));
                    }
                });
            });

        return $hallazgos->values();
    }

    /** Cuentas distintas que comparten la combinación de campos indicada. */
    private function chequearSinDuplicados(EntraRegla $regla, Collection $universo): Collection
    {
        $campos = $regla->config['campos'] ?? ['displayName'];

        $hallazgos = collect();

        $universo
            ->filter(function ($u) use ($campos) {
                // Solo evalúa cuentas que tengan todos los campos con valor
                foreach ($campos as $c) {
                    if (trim((string) ($u[$c] ?? '')) === '') return false;
                }
                return true;
            })
            ->groupBy(function ($u) use ($campos) {
                return collect($campos)
                    ->map(fn($c) => $this->normalizar($u[$c]))
                    ->join('|');
            })
            ->filter(fn(Collection $grupo) => $grupo->count() > 1)
            ->each(function (Collection $grupo) use ($campos, &$hallazgos) {
                $etiqueta = collect($campos)
                    ->map(fn($c) => trim((string) ($grupo->first()[$c] ?? '')))
                    ->filter()
                    ->join(' ');

                foreach ($grupo as $u) {
                    $hallazgos->push($this->hallazgo($u, [
                        'detalle' => $etiqueta,
                        'motivo'  => $grupo->count() . ' cuentas comparten estos datos',
                        'grupo'   => $etiqueta,
                    ]));
                }
            });

        return $hallazgos->values();
    }

    /** Cuentas sin inicio de sesión dentro del plazo configurado. */
    private function chequearActividadReciente(EntraRegla $regla, Collection $universo): Collection
    {
        $dias  = (int) ($regla->config['dias'] ?? 90);
        $corte = now()->subDays($dias);

        return $universo
            ->map(function ($u) {
                $u['_ultimo_login'] = $this->ultimoLogin($u);
                return $u;
            })
            ->filter(function ($u) use ($corte) {
                $ultimo = $u['_ultimo_login'];
                return $ultimo === null || $ultimo->lt($corte);
            })
            ->map(function ($u) {
                $ultimo    = $u['_ultimo_login'];
                $inactivos = $ultimo ? (int) floor($ultimo->diffInDays(now())) : null;

                return $this->hallazgo($u, [
                    'detalle' => $ultimo?->format('d/m/Y'),
                    'motivo'  => $ultimo === null
                        ? 'Nunca ha iniciado sesión'
                        : 'Último acceso hace ' . number_format($inactivos) . ' días',
                    'dias_inactivo' => $inactivos,
                ]);
            })
            ->sortByDesc(fn($h) => $h['dias_inactivo'] ?? PHP_INT_MAX)
            ->values();
    }

    /* ── Utilidades ──────────────────────────────────────────────────────── */

    /** Extrae la fecha de último inicio de sesión (interactivo o no). */
    private function ultimoLogin(array $u): ?\Illuminate\Support\Carbon
    {
        $act = $u['signInActivity'] ?? null;
        if (!is_array($act)) return null;

        $fechas = collect([
            $act['lastSignInDateTime'] ?? null,
            $act['lastNonInteractiveSignInDateTime'] ?? null,
        ])->filter()->map(fn($f) => \Illuminate\Support\Carbon::parse($f));

        return $fechas->isEmpty() ? null : $fechas->max();
    }

    /**
     * Puntúa qué tan bien escrito está un texto, para desempatar variantes:
     * 2 = capitalización normal, 1 = todo minúsculas, 0 = TODO MAYÚSCULAS.
     */
    private function calidadEscritura(string $valor): int
    {
        $letras = preg_replace('/[^\p{L}]/u', '', $valor);
        if ($letras === '') return 1;

        if ($letras === mb_strtoupper($letras)) return 0; // grita
        if ($letras === mb_strtolower($letras)) return 1; // todo minúscula
        return 2;                                          // Capitalización mixta
    }

    /**
     * Normaliza un valor para comparar: minúsculas, sin tildes,
     * sin espacios repetidos ni signos de puntuación al borde.
     */
    private function normalizar(mixed $valor): string
    {
        $s = mb_strtolower(trim((string) $valor));

        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i',
            'ò' => 'o', 'ù' => 'u', 'ç' => 'c',
        ]);

        $s = preg_replace('/[\s\._\-]+/u', ' ', $s);   // separadores → espacio
        $s = preg_replace('/[^\p{L}\p{N} ]/u', '', $s); // fuera puntuación
        return trim($s);
    }

    /** Da forma uniforme a una fila de hallazgo. */
    private function hallazgo(array $u, array $extra = []): array
    {
        return array_merge([
            'id'          => $u['id'] ?? null,
            'nombre'      => $u['displayName'] ?? ($u['userPrincipalName'] ?? '—'),
            'upn'         => $u['userPrincipalName'] ?? null,
            'mail'        => $u['mail'] ?? null,
            'department'  => $u['department'] ?? null,
            'jobTitle'    => $u['jobTitle'] ?? null,
            'habilitada'  => ($u['accountEnabled'] ?? true) !== false,
        ], $extra);
    }
}
