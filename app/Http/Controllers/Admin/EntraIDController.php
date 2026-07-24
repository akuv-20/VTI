<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\EntraRegla;
use App\Services\EntraAuditor;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class EntraIDController extends Controller
{
    private string $graphBase = 'https://graph.microsoft.com/v1.0';

    // ── Token de acceso con client credentials ────────────────────────────────

    private function getAccessToken(): string
    {
        $tenantId = Configuracion::get('azure_tenant_id');

        return Cache::remember('entra_id_token', 3500, function () use ($tenantId) {
            $clientId     = Configuracion::get('azure_client_id');
            $clientSecret = Configuracion::get('azure_client_secret');

            if (!$tenantId || !$clientId || !$clientSecret) {
                throw new \RuntimeException('Azure no configurado. Completa client_id, client_secret y tenant_id en Admin → Configuración.');
            }

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                ]
            );

            if (!$response->successful()) {
                $err = $response->json('error_description') ?? $response->body();
                throw new \RuntimeException('No se pudo obtener token de Azure: ' . $err);
            }

            return $response->json('access_token');
        });
    }

    // ── Listado de usuarios ───────────────────────────────────────────────────

    public function datos(Request $request)
    {
        try {
            $token  = $this->getAccessToken();
            $todos  = Cache::remember(self::CACHE_USERS_KEY, self::CACHE_USERS_TTL, fn() => $this->fetchFromGraph($token));
            return response()->json($todos->values());
        } catch (\Throwable $e) {
            Cache::forget('entra_id_token');
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $buscar       = trim($request->input('buscar', ''));
            $filtroEstado = $request->input('estado', 'habilitados');
            $perPage      = 40;
            $page         = (int) $request->input('page', 1);

            $token = $this->getAccessToken();

            // Graph: traer todos los usuarios (paginamos nosotros para poder filtrar por estado)
            $usuarios = $this->fetchAllUsers($token, $buscar);

            $countHabilitados    = $usuarios->filter(fn($u) => !($u['accountEnabled'] === false))->count();
            $countDeshabilitados = $usuarios->filter(fn($u) =>   ($u['accountEnabled'] === false))->count();
            $countTodos          = $usuarios->count();

            if ($filtroEstado === 'habilitados') {
                $usuarios = $usuarios->filter(fn($u) => $u['accountEnabled'] !== false);
            } elseif ($filtroEstado === 'deshabilitados') {
                $usuarios = $usuarios->filter(fn($u) => $u['accountEnabled'] === false);
            }

            $usuarios = $usuarios->values();
            $total    = $usuarios->count();
            $slice    = $usuarios->slice(($page - 1) * $perPage, $perPage)->values();

            $paginados = new LengthAwarePaginator($slice, $total, $perPage, $page, [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]);

            return view('admin.entra_id.index', [
                'usuarios'            => $paginados,
                'buscar'              => $buscar,
                'filtroEstado'        => $filtroEstado,
                'total'               => $total,
                'countHabilitados'    => $countHabilitados,
                'countDeshabilitados' => $countDeshabilitados,
                'countTodos'          => $countTodos,
            ]);

        } catch (\Throwable $e) {
            Cache::forget('entra_id_token');
            return view('admin.entra_id.index', [
                'usuarios'            => null,
                'buscar'              => $request->input('buscar', ''),
                'filtroEstado'        => $request->input('estado', 'habilitados'),
                'total'               => 0,
                'countHabilitados'    => 0,
                'countDeshabilitados' => 0,
                'countTodos'          => 0,
                'graphError'          => $e->getMessage(),
            ]);
        }
    }

    // ── Value Inspector ───────────────────────────────────────────────────────

    // Campos inspeccionables: clave Graph → etiqueta legible
    private array $camposInspector = [
        'country'        => 'País',
        'department'     => 'Área / Departamento',
        'jobTitle'       => 'Cargo',
        'officeLocation' => 'Oficina',
        'userType'       => 'Tipo de cuenta',
        'companyName'    => 'Empresa',
    ];

    // Valores canónicos conocidos por campo (para marcar inconsistencias)
    private array $valoresEsperados = [
        'country'  => ['CL', 'PE', 'AR', 'US', 'ES'],
        'userType' => ['Member', 'Guest'],
    ];

    private function computeResumen(\Illuminate\Support\Collection $todos): array
    {
        $resumen = [];
        foreach ($this->camposInspector as $campo => $etiqueta) {
            $esperados = $this->valoresEsperados[$campo] ?? null;

            $grupos = $todos
                ->groupBy(fn($u) => $u[$campo] ?? '')
                ->map(fn($grupo, $valor) => [
                    'valor'         => $valor,
                    'count'         => $grupo->count(),
                    'inconsistente' => $esperados !== null && $valor !== '' && !in_array($valor, $esperados),
                    'vacio'         => $valor === '',
                ])
                ->sortByDesc('count')
                ->values();

            $resumen[$campo] = [
                'etiqueta'    => $etiqueta,
                'valores'     => $grupos,
                'tiene_regla' => $esperados !== null,
                'total_ok'    => $grupos->filter(fn($g) => !$g['vacio'] && !$g['inconsistente'])->sum('count'),
                'total_vacio' => $grupos->filter(fn($g) => $g['vacio'])->sum('count'),
                'total_inc'   => $grupos->filter(fn($g) => $g['inconsistente'])->sum('count'),
            ];
        }
        return $resumen;
    }

    public function dashboard(Request $request)
    {
        try {
            $token = $this->getAccessToken();
            [$todos, $tieneSignIn] = $this->fetchUsuariosConActividad($token);

            $total          = $todos->count();
            $habilitados    = $todos->filter(fn($u) => ($u['accountEnabled'] ?? true) !== false)->count();
            $deshabilitados = $total - $habilitados;

            $reglas    = EntraRegla::activas()->ordenadas()->get();
            $auditor   = new EntraAuditor($todos, $tieneSignIn);
            $resultados = collect($auditor->evaluar($reglas));

            // Puntuación global: promedio de las reglas que sí se pudieron evaluar
            $evaluables  = $resultados->filter(fn($r) => $r['disponible']);
            $scoreGlobal = $evaluables->isEmpty() ? 100.0 : round($evaluables->avg('score'), 1);

            $totalHallazgos = $evaluables->sum('fallos');

            return view('admin.entra_id.dashboard', [
                'resultados'     => $resultados,
                'total'          => $total,
                'habilitados'    => $habilitados,
                'deshabilitados' => $deshabilitados,
                'scoreGlobal'    => $scoreGlobal,
                'totalHallazgos' => $totalHallazgos,
                'tieneSignIn'    => $tieneSignIn,
            ]);

        } catch (\Throwable $e) {
            Cache::forget('entra_id_token');
            return view('admin.entra_id.dashboard', [
                'resultados'     => collect(),
                'total'          => 0,
                'habilitados'    => 0,
                'deshabilitados' => 0,
                'scoreGlobal'    => 0,
                'totalHallazgos' => 0,
                'tieneSignIn'    => false,
                'graphError'     => $e->getMessage(),
            ]);
        }
    }

    /** Detalle de las cuentas que incumplen una regla. */
    public function hallazgos(Request $request, EntraRegla $regla)
    {
        try {
            $token = $this->getAccessToken();
            [$todos, $tieneSignIn] = $this->fetchUsuariosConActividad($token);

            $auditor   = new EntraAuditor($todos, $tieneSignIn);
            $resultado = $auditor->evaluarRegla($regla);

            return view('admin.entra_id.hallazgos', [
                'regla'     => $regla,
                'resultado' => $resultado,
            ]);

        } catch (\Throwable $e) {
            Cache::forget('entra_id_token');
            return back()->withErrors([$e->getMessage()]);
        }
    }

    // ── CRUD de reglas ───────────────────────────────────────────────────────

    public function reglas()
    {
        return view('admin.entra_id.reglas', [
            'reglas' => EntraRegla::ordenadas()->get(),
        ]);
    }

    public function reglaStore(Request $request)
    {
        $data = $this->validarRegla($request);
        EntraRegla::create($data);

        return redirect()->route('admin.entra_id.reglas')
            ->with('success', 'Regla creada.');
    }

    public function reglaUpdate(Request $request, EntraRegla $regla)
    {
        $data = $this->validarRegla($request);
        $regla->update($data);

        return redirect()->route('admin.entra_id.reglas')
            ->with('success', 'Regla actualizada.');
    }

    public function reglaToggle(EntraRegla $regla)
    {
        $regla->update(['activa' => !$regla->activa]);

        return back()->with('success', $regla->activa ? 'Regla activada.' : 'Regla desactivada.');
    }

    public function reglaDestroy(EntraRegla $regla)
    {
        $regla->delete();

        return redirect()->route('admin.entra_id.reglas')
            ->with('success', 'Regla eliminada.');
    }

    private function validarRegla(Request $request): array
    {
        $validated = $request->validate([
            'tipo'             => ['required', 'in:' . implode(',', array_keys(EntraRegla::TIPOS))],
            'campo'            => ['nullable', 'in:' . implode(',', array_keys(EntraRegla::CAMPOS))],
            'etiqueta'         => ['required', 'string', 'max:150'],
            'descripcion'      => ['nullable', 'string', 'max:500'],
            'severidad'        => ['required', 'in:error,advertencia'],
            'solo_habilitados' => ['nullable', 'boolean'],
            'orden'            => ['nullable', 'integer', 'min:0', 'max:9999'],
            // Config según tipo
            'valores'          => ['nullable', 'string'],   // separados por coma o salto de línea
            'campos_dup'       => ['nullable', 'array'],
            'campos_dup.*'     => ['in:' . implode(',', array_keys(EntraRegla::CAMPOS))],
            'dias'             => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $tipo = $validated['tipo'];

        // El campo es obligatorio solo para los tipos que lo requieren
        if (EntraRegla::TIPOS[$tipo]['requiere_campo'] && empty($validated['campo'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'campo' => 'Este tipo de regla necesita un campo.',
            ]);
        }

        $config = match ($tipo) {
            'valores_permitidos' => [
                'valores' => collect(preg_split('/[\r\n,;]+/', $validated['valores'] ?? ''))
                    ->map(fn($v) => trim($v))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ],
            'sin_duplicados' => [
                'campos' => $validated['campos_dup'] ?? ['displayName'],
            ],
            'actividad_reciente' => [
                'dias' => $validated['dias'] ?? 90,
            ],
            default => null,
        };

        if ($tipo === 'valores_permitidos' && empty($config['valores'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'valores' => 'Indica al menos un valor permitido.',
            ]);
        }

        return [
            'tipo'             => $tipo,
            'campo'            => EntraRegla::TIPOS[$tipo]['requiere_campo'] ? $validated['campo'] : null,
            'etiqueta'         => $validated['etiqueta'],
            'descripcion'      => $validated['descripcion'] ?? null,
            'config'           => $config,
            'severidad'        => $validated['severidad'],
            'solo_habilitados' => $request->boolean('solo_habilitados'),
            'orden'            => $validated['orden'] ?? 0,
        ];
    }

    public function inspector(Request $request)
    {
        try {
            $token   = $this->getAccessToken();
            $todos   = $this->fetchAllUsers($token);
            $resumen = $this->computeResumen($todos);

            return view('admin.entra_id.inspector', [
                'resumen'   => $resumen,
                'totalUSer' => $todos->count(),
            ]);

        } catch (\Throwable $e) {
            Cache::forget('entra_id_token');
            return view('admin.entra_id.inspector', [
                'resumen'    => [],
                'totalUSer'  => 0,
                'graphError' => $e->getMessage(),
            ]);
        }
    }

    public function inspectorDetalle(Request $request, string $campo)
    {
        abort_unless(array_key_exists($campo, $this->camposInspector), 404);

        try {
            $valor   = $request->input('valor', '');  // '' = vacíos
            $token   = $this->getAccessToken();
            $todos   = $this->fetchAllUsers($token);

            $filtrados = $todos->filter(function ($u) use ($campo, $valor) {
                $v = $u[$campo] ?? '';
                return $v === $valor;
            })->values();

            return view('admin.entra_id.inspector_detalle', [
                'campo'    => $campo,
                'etiqueta' => $this->camposInspector[$campo],
                'valor'    => $valor,
                'usuarios' => $filtrados,
            ]);

        } catch (\Throwable $e) {
            Cache::forget('entra_id_token');
            return back()->withErrors([$e->getMessage()]);
        }
    }

    // ── Traer todos los usuarios (con caché de 5 min) ────────────────────────

    private const CACHE_USERS_KEY = 'entra_id_users_all';
    private const CACHE_USERS_TTL = 300; // segundos

    private function fetchAllUsers(string $token, string $buscar = '', string $fields = ''): \Illuminate\Support\Collection
    {
        $todos = Cache::remember(self::CACHE_USERS_KEY, self::CACHE_USERS_TTL, function () use ($token) {
            return $this->fetchFromGraph($token);
        });

        if ($buscar !== '') {
            $q = mb_strtolower($buscar);
            $todos = $todos->filter(function ($u) use ($q) {
                return str_contains(mb_strtolower($u['displayName']        ?? ''), $q)
                    || str_contains(mb_strtolower($u['userPrincipalName']  ?? ''), $q)
                    || str_contains(mb_strtolower($u['mail']               ?? ''), $q)
                    || str_contains(mb_strtolower($u['department']         ?? ''), $q)
                    || str_contains(mb_strtolower($u['jobTitle']           ?? ''), $q);
            });
        }

        return $todos;
    }

    private function fetchFromGraph(string $token): \Illuminate\Support\Collection
    {
        $fields = 'id,displayName,givenName,surname,userPrincipalName,mail,jobTitle,department,city,state,country,usageLocation,officeLocation,mobilePhone,businessPhones,accountEnabled,createdDateTime,userType,companyName';

        $url   = $this->graphBase . "/users?\$top=999&\$select={$fields}&\$orderby=displayName";
        $todos = collect();

        do {
            $resp = Http::withHeaders(['Authorization' => 'Bearer ' . $token])->get($url);

            if (!$resp->successful()) {
                throw new \RuntimeException('Error Graph API: ' . ($resp->json('error.message') ?? $resp->body()));
            }

            $data  = $resp->json();
            $todos = $todos->concat($data['value'] ?? []);
            $url   = $data['@odata.nextLink'] ?? null;

        } while ($url && $todos->count() < 5000);

        return $todos;
    }

    // ── Actividad de inicio de sesión ────────────────────────────────────────
    //
    // signInActivity vive en otra consulta: exige el permiso AuditLog.Read.All
    // y Graph no lo admite junto con $orderby. Si el permiso no está otorgado
    // devolvemos null y las reglas que dependen de él quedan "no disponibles".

    private const CACHE_SIGNIN_KEY = 'entra_id_signin_activity';

    private function fetchSignInActivity(string $token): ?array
    {
        return Cache::remember(self::CACHE_SIGNIN_KEY, self::CACHE_USERS_TTL, function () use ($token) {
            $url   = $this->graphBase . '/users?$top=999&$select=id,signInActivity';
            $mapa  = [];
            $leidos = 0;

            do {
                $resp = Http::withHeaders(['Authorization' => 'Bearer ' . $token])->get($url);

                if (!$resp->successful()) {
                    return null; // sin permiso u otro error → degradamos
                }

                $data = $resp->json();
                foreach ($data['value'] ?? [] as $fila) {
                    if (!empty($fila['id'])) {
                        $mapa[$fila['id']] = $fila['signInActivity'] ?? null;
                    }
                }
                $leidos += count($data['value'] ?? []);
                $url = $data['@odata.nextLink'] ?? null;

            } while ($url && $leidos < 5000);

            return $mapa;
        });
    }

    /**
     * Usuarios con signInActivity incorporado.
     * Devuelve [colección, bool $tieneSignIn].
     */
    private function fetchUsuariosConActividad(string $token): array
    {
        $usuarios = $this->fetchAllUsers($token);
        $mapa     = $this->fetchSignInActivity($token);

        if ($mapa === null) {
            return [$usuarios, false];
        }

        $usuarios = $usuarios->map(function ($u) use ($mapa) {
            $u['signInActivity'] = $mapa[$u['id'] ?? ''] ?? null;
            return $u;
        });

        return [$usuarios, true];
    }
}
