<?php

namespace App\Http\Controllers;

use App\Services\AsignacionTickets;
use App\Services\GlpiApi;
use App\Services\GlpiApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Asistencia GLPI → Asignación: mesa para repartir los tickets nuevos entre
 * los técnicos y reasignar los que ya tienen dueño.
 *
 * Además del módulo en VTI, asignar exige un perfil de GLPI de los que pueden
 * hacerlo (Supervisor, Admin, Super-Admin). El usuario de GLPI se obtiene del
 * correo con que se entró a VTI (los dos vienen de Entra ID), y lo que se
 * escribe en GLPI va con el token personal de esa persona.
 *
 * La pantalla se arma con datos que pide por AJAX y refresca sola; las
 * acciones responden JSON.
 */
class AsignacionController extends Controller
{
    /* ── Pantalla ─────────────────────────────────────────────────────────── */

    public function index()
    {
        $usuario = auth()->user();

        try {
            $glpi = $this->usuarioGlpi();
        } catch (\Throwable $e) {
            report($e);
            return view('asistencia.asignacion_aviso', ['motivo' => 'glpi_caido', 'detalle' => $e->getMessage()]);
        }

        if (!$glpi) {
            return view('asistencia.asignacion_aviso', ['motivo' => 'sin_usuario', 'correo' => $usuario->email]);
        }

        if (!AsignacionTickets::puedeAsignar($glpi)) {
            return view('asistencia.asignacion_aviso', ['motivo' => 'sin_perfil', 'glpi' => $glpi]);
        }

        if (!GlpiApi::configurada()) {
            return view('asistencia.asignacion_aviso', ['motivo' => 'sin_app_token']);
        }

        if (!$usuario->glpi_api_token) {
            return view('asistencia.asignacion_token', ['glpi' => $glpi, 'urlGlpi' => GlpiApi::urlWeb()]);
        }

        return view('asistencia.asignacion', ['glpi' => $glpi, 'urlGlpi' => GlpiApi::urlWeb()]);
    }

    /* ── Token personal ───────────────────────────────────────────────────── */

    /**
     * Guarda el token personal después de probarlo contra GLPI y comprobar
     * que pertenece a quien está conectado: con el token de otro, todo lo que
     * asigne quedaría a nombre de esa otra persona.
     */
    public function guardarToken(Request $request)
    {
        $request->validate(['token' => 'required|string|min:20|max:100']);

        $glpi = $this->usuarioGlpi();
        abort_if(!$glpi || !AsignacionTickets::puedeAsignar($glpi), 403);

        $token = trim($request->input('token'));

        try {
            $dueno = (new GlpiApi($token))->idUsuario();
        } catch (GlpiApiException $e) {
            return back()->withErrors(['token' => $e->esTokenInvalido()
                ? 'GLPI no reconoce ese token. Revisa que lo hayas copiado completo y que hayas guardado los cambios en GLPI después de regenerarlo.'
                : $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['token' => 'No se pudo conectar con GLPI: ' . $e->getMessage()]);
        }

        if ($dueno !== $glpi['id']) {
            return back()->withErrors(['token' => 'Ese token pertenece a otro usuario de GLPI. Debes usar el tuyo.']);
        }

        $usuario = auth()->user();
        $usuario->glpi_api_token = $token;
        $usuario->save();

        return redirect()->route('asistencia.asignacion')->with('success', 'Token de GLPI guardado. Ya puedes asignar tickets.');
    }

    public function olvidarToken()
    {
        $usuario = auth()->user();
        $usuario->glpi_api_token = null;
        $usuario->save();

        return redirect()->route('asistencia.asignacion')->with('success', 'Token de GLPI eliminado.');
    }

    /* ── Datos para la pantalla (JSON) ────────────────────────────────────── */

    public function nuevos(): JsonResponse
    {
        return $this->leer(fn(AsignacionTickets $s) => ['tickets' => $s->nuevos()]);
    }

    public function asignados(): JsonResponse
    {
        return $this->leer(fn(AsignacionTickets $s) => ['tickets' => $s->asignados()]);
    }

    public function tecnicos(): JsonResponse
    {
        return $this->leer(fn(AsignacionTickets $s) => $s->tecnicos());
    }

    public function detalle(int $ticket): JsonResponse
    {
        return $this->leer(function (AsignacionTickets $s) use ($ticket) {
            $d = $s->detalle($ticket);
            abort_if(!$d, 404);
            return ['ticket' => $d];
        });
    }

    /* ── Acciones ─────────────────────────────────────────────────────────── */

    /**
     * Asigna o reasigna un ticket.
     *
     * La pantalla manda los técnicos que VE en el ticket (`esperados`). Si en
     * GLPI ya no son esos —otra persona lo asignó mientras tanto— no se toca
     * nada y se responde 409 con el estado real, para no pisar la decisión
     * de nadie.
     */
    public function asignar(Request $request, int $ticket): JsonResponse
    {
        $datos = $request->validate([
            'tecnico'      => 'required|integer',
            'esperados'    => 'array',
            'esperados.*'  => 'integer',
            'nota'         => 'nullable|string|max:2000',
            'nota_privada' => 'boolean',
        ]);

        if ($error = $this->verificarPermiso()) return $error;

        $servicio = new AsignacionTickets();

        try {
            $estado = $servicio->estado($ticket);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'No se pudo leer el ticket en GLPI.'], 503);
        }

        if (!$estado) return response()->json(['error' => 'El ticket ya no existe en GLPI.'], 404);
        if ($estado['estado'] >= 5) return response()->json(['error' => 'El ticket ya está ' . mb_strtolower(AsignacionTickets::ESTADOS[$estado['estado']]) . '.'], 409);

        $actuales  = collect($estado['tecnicos'])->pluck('id')->filter()->sort()->values()->all();
        $esperados = collect($datos['esperados'] ?? [])->sort()->values()->all();

        if ($actuales !== $esperados) {
            $quienes = collect($estado['tecnicos'])->pluck('nombre')->implode(', ') ?: 'nadie';
            return response()->json([
                'error'    => "El ticket cambió mientras lo mirabas: ahora lo tiene {$quienes}.",
                'cambiado' => true,
            ], 409);
        }

        // Validar contra la lista real de técnicos: el id viene del navegador.
        $tecnicos = collect($servicio->tecnicos()['tecnicos'])->keyBy('id');
        if (!$tecnicos->has($datos['tecnico'])) {
            return response()->json(['error' => 'Ese técnico no está en ningún grupo asignable de GLPI.'], 422);
        }

        if (in_array($datos['tecnico'], $actuales, true)) {
            return response()->json(['error' => 'El ticket ya está asignado a ese técnico.'], 409);
        }

        // Reasignar = quitar a los técnicos actuales y poner al nuevo.
        $quitar = collect($estado['tecnicos'])->pluck('vinculo')->all();

        try {
            (new GlpiApi(auth()->user()->glpi_api_token))
                ->asignar($ticket, $datos['tecnico'], $quitar, $datos['nota'] ?? null, (bool) ($datos['nota_privada'] ?? false));
        } catch (GlpiApiException $e) {
            if ($e->esTokenInvalido()) {
                return response()->json(['error' => 'GLPI rechazó tu token personal (¿lo regeneraste?). Vuelve a ingresarlo.', 'token_invalido' => true], 401);
            }
            report($e);
            return response()->json(['error' => $e->getMessage()], 502);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'No se pudo conectar con GLPI: ' . $e->getMessage()], 502);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => "Ticket #{$ticket} asignado a {$tecnicos[$datos['tecnico']]['nombre']}.",
        ]);
    }

    /**
     * Da país (Chile/Perú) a un técnico que no tiene ubicación en GLPI, para
     * que entre en el orden por país. Solo si no tiene ninguna: no se pisa
     * una ubicación que alguien ya definió.
     */
    public function paisTecnico(Request $request, int $tecnico): JsonResponse
    {
        $datos = $request->validate(['pais' => 'required|string|max:50']);

        if ($error = $this->verificarPermiso()) return $error;

        $servicio = new AsignacionTickets();

        try {
            $paises = $servicio->paises();
            $tec    = collect($servicio->tecnicos()['tecnicos'])->firstWhere('id', $tecnico);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'No se pudo leer GLPI.'], 503);
        }

        if (!isset($paises[$datos['pais']])) {
            return response()->json(['error' => 'País desconocido.'], 422);
        }
        if (!$tec) {
            return response()->json(['error' => 'Ese técnico no está en ningún grupo asignable de GLPI.'], 422);
        }
        if ($tec['pais']) {
            return response()->json(['error' => "{$tec['nombre']} ya tiene país: {$tec['pais']}.", 'pais' => $tec['pais']], 409);
        }

        try {
            (new GlpiApi(auth()->user()->glpi_api_token))->fijarUbicacion($tecnico, $paises[$datos['pais']]);
        } catch (GlpiApiException $e) {
            if ($e->esTokenInvalido()) {
                return response()->json(['error' => 'GLPI rechazó tu token personal (¿lo regeneraste?). Vuelve a ingresarlo.', 'token_invalido' => true], 401);
            }
            report($e);
            // El caso típico: el perfil Supervisor no tiene permiso para editar usuarios.
            return response()->json(['error' => $e->estadoHttp === 403
                ? 'Tu perfil de GLPI no puede editar usuarios. Pide a un administrador de GLPI que le asigne la ubicación.'
                : $e->getMessage()], 502);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'No se pudo conectar con GLPI: ' . $e->getMessage()], 502);
        }

        return response()->json(['ok' => true, 'pais' => $datos['pais'], 'mensaje' => "{$tec['nombre']} quedó en {$datos['pais']}."]);
    }

    /* ── Apoyo ────────────────────────────────────────────────────────────── */

    /**
     * Usuario de GLPI de quien está conectado. Se cachea unos minutos: lo
     * consultan todas las llamadas AJAX y cambia muy rara vez.
     */
    private function usuarioGlpi(): ?array
    {
        $usuario = auth()->user();

        return Cache::remember("asignacion_glpi_usuario_{$usuario->id}", 300,
            fn() => (new AsignacionTickets())->usuarioPorCorreo($usuario->email));
    }

    /** null si puede asignar; si no, la respuesta de error. */
    private function verificarPermiso(): ?JsonResponse
    {
        try {
            $glpi = $this->usuarioGlpi();
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'No se pudo consultar GLPI.'], 503);
        }

        if (!$glpi || !AsignacionTickets::puedeAsignar($glpi)) {
            return response()->json(['error' => 'Tu perfil de GLPI no permite asignar tickets.'], 403);
        }

        if (!auth()->user()->glpi_api_token) {
            return response()->json(['error' => 'Falta tu token personal de GLPI.', 'token_invalido' => true], 401);
        }

        return null;
    }

    /** Lectura para la pantalla, con el mismo control de permiso y errores en JSON. */
    private function leer(\Closure $consulta): JsonResponse
    {
        if ($error = $this->verificarPermiso()) return $error;

        try {
            return response()->json($consulta(new AsignacionTickets()) + ['generado' => now()->toIso8601String()]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'No se pudo leer GLPI: ' . $e->getMessage()], 503);
        }
    }
}
