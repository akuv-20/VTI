<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
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

    public function inspector(Request $request)
    {
        try {
            $token   = $this->getAccessToken();
            $campos  = array_keys($this->camposInspector);
            $fields  = 'id,displayName,userPrincipalName,accountEnabled,' . implode(',', $campos);
            $todos   = $this->fetchAllUsers($token, '', $fields);

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
                    'total_ok'    => $grupos->filter(fn($g) => !$g['vacio'] && !$g['inconsistente'])->sum('count'),
                    'total_vacio' => $grupos->filter(fn($g) => $g['vacio'])->sum('count'),
                    'total_inc'   => $grupos->filter(fn($g) => $g['inconsistente'])->sum('count'),
                ];
            }

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
            $campos  = array_keys($this->camposInspector);
            $fields  = 'id,displayName,userPrincipalName,mail,accountEnabled,' . implode(',', $campos);
            $todos   = $this->fetchAllUsers($token, '', $fields);

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

    // ── Traer todos los usuarios desde Graph (con paginación interna) ─────────

    private function fetchAllUsers(string $token, string $buscar, string $fields = ''): \Illuminate\Support\Collection
    {
        if ($fields === '') {
            $fields = 'id,displayName,givenName,surname,userPrincipalName,mail,jobTitle,department,officeLocation,mobilePhone,businessPhones,accountEnabled,createdDateTime,userType,country,companyName';
        }
        $perCall = 999; // máximo permitido por Graph

        $url = $this->graphBase . "/users?\$top={$perCall}&\$select={$fields}&\$orderby=displayName";

        if ($buscar !== '') {
            // Graph search requiere el header ConsistencyLevel: eventual
            $url .= '&$search="displayName:' . addslashes($buscar) . '" OR "mail:' . addslashes($buscar) . '" OR "userPrincipalName:' . addslashes($buscar) . '" OR "department:' . addslashes($buscar) . '" OR "jobTitle:' . addslashes($buscar) . '"';
        }

        $todos = collect();

        do {
            $headers = ['Authorization' => 'Bearer ' . $token];
            if ($buscar !== '') {
                $headers['ConsistencyLevel'] = 'eventual';
            }

            $resp = Http::withHeaders($headers)->get($url);

            if (!$resp->successful()) {
                throw new \RuntimeException('Error Graph API: ' . ($resp->json('error.message') ?? $resp->body()));
            }

            $data  = $resp->json();
            $items = collect($data['value'] ?? []);
            $todos = $todos->concat($items);

            $url = $data['@odata.nextLink'] ?? null;

        } while ($url && $todos->count() < 5000); // límite de seguridad

        return $todos;
    }
}
