<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lectura de la mesa de asignación: tickets nuevos, tickets ya asignados,
 * técnicos con su carga y el detalle de un ticket. Lee directo de la base del
 * GLPI de Unifrutti (la misma ticketera que el ranking); escribir es cosa de
 * GlpiApi.
 *
 * Igual que en RankingTickets, cada consulta fija la sesión de MySQL en hora
 * de Chile: las fechas son `timestamp` y el servidor está en UTC.
 */
class AsignacionTickets
{
    /** Perfiles de GLPI que pueden asignar desde VTI. */
    public const PERFILES_ASIGNADORES = ['Supervisor', 'Admin', 'Super-Admin'];

    public const ESTADOS = [
        1 => 'Nuevo',
        2 => 'En curso (asignado)',
        3 => 'En curso (planificado)',
        4 => 'En espera',
        5 => 'Resuelto',
        6 => 'Cerrado',
    ];

    /** Ventana de historial para sugerir técnico. */
    private const DIAS_HISTORIAL = 180;

    private DominioInventario $dominio;

    public function __construct()
    {
        $this->dominio = DominioInventario::oFalla(RankingTickets::DOMINIO);
    }

    private function db()
    {
        $db = DB::connection($this->dominio->glpi());
        $db->statement("SET time_zone = '" . now(RankingTickets::ZONA)->format('P') . "'");

        return $db;
    }

    /* ── Quién está asignando ─────────────────────────────────────────────── */

    /**
     * Usuario de GLPI que corresponde a un correo de VTI. Los dos vienen de
     * Entra ID, así que el login de GLPI es el mismo correo; si no, se busca
     * entre los correos registrados del usuario.
     *
     * @return array{id:int,nombre:string,perfiles:string[]}|null
     */
    public function usuarioPorCorreo(string $correo): ?array
    {
        $db = $this->db();

        $u = $db->table('glpi_users')->where('name', $correo)->where('is_deleted', 0)->first(['id', 'firstname', 'realname', 'name'])
            ?? $db->table('glpi_users as u')
                ->join('glpi_useremails as e', 'e.users_id', '=', 'u.id')
                ->where('e.email', $correo)->where('u.is_deleted', 0)
                ->first(['u.id', 'u.firstname', 'u.realname', 'u.name']);

        if (!$u) return null;

        $perfiles = $db->table('glpi_profiles_users as pu')
            ->join('glpi_profiles as p', 'p.id', '=', 'pu.profiles_id')
            ->where('pu.users_id', $u->id)
            ->distinct()->pluck('p.name')->all();

        return ['id' => (int) $u->id, 'nombre' => self::nombre($u), 'perfiles' => $perfiles];
    }

    public static function puedeAsignar(array $usuarioGlpi): bool
    {
        return (bool) array_intersect($usuarioGlpi['perfiles'], self::PERFILES_ASIGNADORES);
    }

    /* ── Colas ────────────────────────────────────────────────────────────── */

    /** Columnas comunes a las dos colas, sobre glpi_tickets aliasada `t`. */
    private function baseCola()
    {
        return $this->db()->table('glpi_tickets as t')
            ->leftJoin('glpi_itilcategories as c', 'c.id', '=', 't.itilcategories_id')
            ->leftJoin('glpi_locations as l', 'l.id', '=', 't.locations_id')
            ->where('t.is_deleted', 0)
            ->select('t.id', 't.name', 't.content', 't.date', 't.date_mod', 't.status', 't.itilcategories_id',
                     'c.completename as categoria', 'l.completename as ubicacion')
            ->selectSub(fn($q) => $q->from('glpi_documents_items as di')->selectRaw('count(*)')
                ->whereColumn('di.items_id', 't.id')->where('di.itemtype', 'Ticket'), 'adjuntos');
    }

    /** Tickets «Nuevo» sin técnico: lo que hay que repartir. */
    public function nuevos(): array
    {
        $tickets = $this->baseCola()
            ->where('t.status', 1)
            ->whereNotExists(fn($q) => $q->selectRaw('1')->from('glpi_tickets_users as x')
                ->whereColumn('x.tickets_id', 't.id')->where('x.type', 2))
            ->orderBy('t.date')
            ->get();

        return $this->completar($tickets);
    }

    /** Tickets abiertos que ya tienen técnico: para revisar y reasignar. */
    public function asignados(): array
    {
        $tickets = $this->baseCola()
            ->whereIn('t.status', [1, 2, 3, 4])
            ->whereExists(fn($q) => $q->selectRaw('1')->from('glpi_tickets_users as x')
                ->whereColumn('x.tickets_id', 't.id')->where('x.type', 2))
            ->orderBy('t.date')
            ->get();

        return $this->completar($tickets);
    }

    /**
     * Agrega a cada ticket sus actores (solicitante, técnicos, grupos) con una
     * consulta por tipo, en vez de una por ticket.
     */
    private function completar(Collection $tickets): array
    {
        if ($tickets->isEmpty()) return [];

        $ids    = $tickets->pluck('id')->all();
        $actores = $this->actores($ids);
        $grupos = $this->db()->table('glpi_groups_tickets as gt')
            ->join('glpi_groups as g', 'g.id', '=', 'gt.groups_id')
            ->whereIn('gt.tickets_id', $ids)->where('gt.type', 2)
            ->get(['gt.tickets_id', 'g.id', 'g.completename'])
            ->groupBy('tickets_id');

        $ahora = now(RankingTickets::ZONA);

        return $tickets->map(function ($t) use ($actores, $grupos, $ahora) {
            $fecha = Carbon::parse($t->date, RankingTickets::ZONA);
            $act   = $actores[$t->id] ?? ['solicitantes' => [], 'tecnicos' => []];

            // País: el del ticket y, si no trae ubicación, el del solicitante.
            $pais = self::pais($t->ubicacion)
                ?? collect($act['solicitantes'])->map(fn($s) => self::pais($s['ubicacion']))->filter()->first();

            return [
                'id'           => (int) $t->id,
                'pais'         => $pais,
                'titulo'       => html_entity_decode((string) $t->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'resumen'      => Str::limit(self::textoPlano($t->content), 180),
                'fecha'        => $fecha->format('d/m/Y H:i'),
                'horas'        => (int) floor($fecha->diffInMinutes($ahora) / 60),
                'antiguedad'   => self::antiguedad($fecha, $ahora),
                'actualizado'  => $t->date_mod ? Carbon::parse($t->date_mod, RankingTickets::ZONA)->format('d/m/Y H:i') : null,
                'estado'       => (int) $t->status,
                'estado_texto' => self::ESTADOS[$t->status] ?? (string) $t->status,
                'categoria'    => $t->categoria,
                'ubicacion'    => $t->ubicacion,
                'adjuntos'     => (int) $t->adjuntos,
                'grupos'       => ($grupos[$t->id] ?? collect())->map(fn($g) => ['id' => (int) $g->id, 'nombre' => $g->completename])->values()->all(),
                'solicitantes' => $act['solicitantes'],
                'tecnicos'     => $act['tecnicos'],
            ];
        })->values()->all();
    }

    /** Solicitantes y técnicos de varios tickets, agrupados por ticket. */
    private function actores(array $ids): array
    {
        $filas = $this->db()->table('glpi_tickets_users as tu')
            ->leftJoin('glpi_users as u', 'u.id', '=', 'tu.users_id')
            ->leftJoin('glpi_locations as ul', 'ul.id', '=', 'u.locations_id')
            ->whereIn('tu.tickets_id', $ids)->whereIn('tu.type', [1, 2])
            ->get(['tu.id as vinculo', 'tu.tickets_id', 'tu.type', 'tu.alternative_email', 'u.id', 'u.name', 'u.firstname', 'u.realname',
                   'ul.completename as ubicacion']);

        $res = [];
        foreach ($filas as $f) {
            $tipo = (int) $f->type === 1 ? 'solicitantes' : 'tecnicos';
            $res[$f->tickets_id][$tipo][] = [
                'id'      => $f->id ? (int) $f->id : null,
                'vinculo' => (int) $f->vinculo,
                // Un solicitante por correo, sin usuario en GLPI, solo trae el correo.
                'nombre'    => $f->id ? self::nombre($f) : ($f->alternative_email ?: 'Desconocido'),
                'ubicacion' => $f->ubicacion,
            ];
        }

        foreach ($res as &$r) {
            $r += ['solicitantes' => [], 'tecnicos' => []];
        }

        return $res;
    }

    /* ── Técnicos ─────────────────────────────────────────────────────────── */

    /**
     * Grupos asignables con sus miembros activos, y la carga de cada técnico:
     * tickets abiertos que tiene asignados y los que resolvió hoy.
     *
     * Una persona puede estar en varios grupos y atender tickets de cualquiera,
     * por eso la pantalla deja elegir el grupo y ofrece también «Todos».
     *
     * @return array{grupos: array, tecnicos: array}
     */
    public function tecnicos(): array
    {
        $db = $this->db();

        // La ubicación del técnico en GLPI dice de qué país es (basta con
        // «Chile» o «Perú»; si tiene una más específica se toma su raíz).
        $miembros = $db->table('glpi_groups_users as gu')
            ->join('glpi_groups as g', 'g.id', '=', 'gu.groups_id')
            ->join('glpi_users as u', 'u.id', '=', 'gu.users_id')
            ->leftJoin('glpi_locations as l', 'l.id', '=', 'u.locations_id')
            ->where('g.is_assign', 1)->where('u.is_active', 1)->where('u.is_deleted', 0)
            ->get(['g.id as grupo_id', 'g.completename as grupo', 'u.id', 'u.name', 'u.firstname', 'u.realname', 'l.completename as ubicacion']);

        $ids = $miembros->pluck('id')->unique()->all();

        $abiertos = $db->table('glpi_tickets_users as tu')
            ->join('glpi_tickets as t', 't.id', '=', 'tu.tickets_id')
            ->where('tu.type', 2)->whereIn('tu.users_id', $ids)
            ->where('t.is_deleted', 0)->whereIn('t.status', [2, 3, 4])
            ->groupBy('tu.users_id')
            ->selectRaw('tu.users_id, count(distinct t.id) n, sum(t.status = 4) en_espera')
            ->get()->keyBy('users_id');

        $hoy = $db->table('glpi_tickets_users as tu')
            ->join('glpi_tickets as t', 't.id', '=', 'tu.tickets_id')
            ->where('tu.type', 2)->whereIn('tu.users_id', $ids)
            ->where('t.is_deleted', 0)->whereIn('t.status', [5, 6])
            ->where('t.solvedate', '>=', now(RankingTickets::ZONA)->startOfDay())
            ->groupBy('tu.users_id')
            ->selectRaw('tu.users_id, count(distinct t.id) n')
            ->pluck('n', 'users_id');

        $tecnicos = $miembros->groupBy('id')->map(function ($filas) use ($abiertos, $hoy) {
            $u = $filas->first();

            return [
                'id'        => (int) $u->id,
                'nombre'    => self::nombre($u),
                'corto'     => self::nombreCorto($u),
                'iniciales' => self::iniciales($u),
                'login'     => Str::before($u->name, '@'),
                'pais'      => self::pais($u->ubicacion),
                'grupos'    => $filas->pluck('grupo_id')->map(fn($g) => (int) $g)->values()->all(),
                'abiertos'  => (int) ($abiertos[$u->id]->n ?? 0),
                'en_espera' => (int) ($abiertos[$u->id]->en_espera ?? 0),
                'hoy'       => (int) ($hoy[$u->id] ?? 0),
            ];
        })->sortBy('nombre')->values()->all();

        $grupos = $miembros->groupBy('grupo_id')
            ->map(fn($f, $id) => ['id' => (int) $id, 'nombre' => $f->first()->grupo, 'miembros' => $f->pluck('id')->unique()->count()])
            ->sortBy('nombre')->values()->all();

        $paises = collect($this->paises())->map(fn($id, $nombre) => ['id' => $id, 'nombre' => $nombre])->values()->all();

        return compact('grupos', 'tecnicos', 'paises');
    }

    /**
     * Países que se le pueden dar a un técnico: las ubicaciones raíz de GLPI
     * («Chile», «Perú»). Se leen del árbol en vez de fijar ids, que son
     * propios de esta base.
     *
     * @return array<string,int> nombre => id de glpi_locations
     */
    public function paises(): array
    {
        return $this->db()->table('glpi_locations')
            ->where('locations_id', 0)
            ->orderBy('completename')
            ->pluck('id', 'completename')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /* ── Detalle ──────────────────────────────────────────────────────────── */

    /** Todo lo que se muestra al abrir un ticket, o null si no existe. */
    public function detalle(int $id): ?array
    {
        $fila = $this->baseCola()->where('t.id', $id)->get();
        if ($fila->isEmpty()) return null;

        $ticket = $this->completar($fila)[0];
        $raw    = $fila->first();
        $web    = GlpiApi::urlWeb();

        $ticket['descripcion'] = self::textoPlano($raw->content, false);
        $ticket['url']         = "{$web}/front/ticket.form.php?id={$id}";
        $ticket['adjuntos_lista'] = $this->adjuntos($id, $web);

        $solicitante = collect($ticket['solicitantes'])->firstWhere('id', '!=', null);
        $ticket['solicitante'] = $solicitante ? $this->solicitante($solicitante['id'], $id) : null;
        $ticket['sugerencias'] = $this->sugerencias($raw, $solicitante['id'] ?? null);

        return $ticket;
    }

    /** Adjuntos del ticket, con enlace a GLPI (abren con la sesión de GLPI del navegador). */
    private function adjuntos(int $id, string $web): array
    {
        return $this->db()->table('glpi_documents_items as di')
            ->join('glpi_documents as d', 'd.id', '=', 'di.documents_id')
            ->where('di.itemtype', 'Ticket')->where('di.items_id', $id)->where('d.is_deleted', 0)
            ->orderBy('d.id')
            ->get(['d.id', 'd.filename', 'd.mime'])
            ->map(fn($d) => [
                'nombre' => $d->filename,
                'imagen' => str_starts_with((string) $d->mime, 'image/'),
                'url'    => "{$web}/front/document.send.php?docid={$d->id}&itemtype=Ticket&items_id={$id}",
            ])->all();
    }

    /** Ficha del solicitante: contacto, equipos del inventario y tickets recientes. */
    private function solicitante(int $usuarioId, int $ticketActual): array
    {
        $db = $this->db();

        $u = $db->table('glpi_users as u')
            ->leftJoin('glpi_locations as l', 'l.id', '=', 'u.locations_id')
            ->where('u.id', $usuarioId)
            ->first(['u.id', 'u.name', 'u.firstname', 'u.realname', 'u.phone', 'u.phone2', 'u.mobile', 'l.completename as ubicacion']);

        $correo = $db->table('glpi_useremails')->where('users_id', $usuarioId)->orderByDesc('is_default')->value('email')
            ?? (str_contains((string) $u->name, '@') ? $u->name : null);

        $equipos = $db->table('glpi_computers as c')
            ->leftJoin('glpi_computermodels as m', 'm.id', '=', 'c.computermodels_id')
            ->where('c.users_id', $usuarioId)->where('c.is_deleted', 0)->where('c.is_template', 0)
            ->orderBy('c.name')
            ->get(['c.name', 'c.serial', 'm.name as modelo'])
            ->map(fn($c) => ['nombre' => $c->name, 'modelo' => $c->modelo, 'serial' => $c->serial])
            ->all();

        $recientes = $db->table('glpi_tickets as t')
            ->join('glpi_tickets_users as tu', fn($j) => $j->on('tu.tickets_id', '=', 't.id')->where('tu.type', 1))
            ->where('tu.users_id', $usuarioId)->where('t.id', '!=', $ticketActual)->where('t.is_deleted', 0)
            ->orderByDesc('t.date')->limit(5)
            ->get(['t.id', 't.name', 't.date', 't.status']);

        $tecRecientes = $recientes->isEmpty() ? [] : $this->actores($recientes->pluck('id')->all());

        return [
            'nombre'    => self::nombre($u),
            'correo'    => $correo,
            'telefonos' => array_values(array_filter([$u->phone, $u->phone2, $u->mobile])),
            'ubicacion' => $u->ubicacion,
            'equipos'   => $equipos,
            'recientes' => $recientes->map(fn($t) => [
                'id'       => (int) $t->id,
                'titulo'   => html_entity_decode((string) $t->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'fecha'    => Carbon::parse($t->date, RankingTickets::ZONA)->format('d/m/Y'),
                'estado'   => self::ESTADOS[$t->status] ?? (string) $t->status,
                'tecnicos' => collect($tecRecientes[$t->id]['tecnicos'] ?? [])->pluck('nombre')->all(),
            ])->all(),
        ];
    }

    /**
     * Técnicos sugeridos para un ticket, con una regla simple y a la vista:
     *   + 1 punto por cada ticket de la MISMA CATEGORÍA que resolvió en los
     *     últimos 180 días,
     *   + 3 puntos por cada ticket de ESTE SOLICITANTE que atendió,
     *   − 1 punto por cada ticket abierto que tiene ahora.
     * Acá se devuelven solo los dos primeros contadores: la carga ya viaja con
     * la lista de técnicos y se resta en la pantalla, que es la que se
     * refresca. Van los motivos, no solo el puntaje, para que quien asigna vea
     * por qué se sugiere a alguien.
     *
     * @return array<int,array{categoria:int,solicitante:int}> por users_id
     */
    private function sugerencias(object $ticket, ?int $solicitanteId): array
    {
        $db    = $this->db();
        $desde = now(RankingTickets::ZONA)->subDays(self::DIAS_HISTORIAL);

        $resueltos = fn() => $db->table('glpi_tickets_users as tu')
            ->join('glpi_tickets as t', 't.id', '=', 'tu.tickets_id')
            ->where('tu.type', 2)->where('t.is_deleted', 0)->whereIn('t.status', [5, 6])
            ->where('t.solvedate', '>=', $desde)
            ->groupBy('tu.users_id');

        $porCategoria = !$ticket->itilcategories_id ? collect() : $resueltos()
            ->where('t.itilcategories_id', $ticket->itilcategories_id)
            ->selectRaw('tu.users_id, count(distinct t.id) n')->pluck('n', 'users_id');

        $porSolicitante = !$solicitanteId ? collect() : $resueltos()
            ->whereExists(fn($q) => $q->selectRaw('1')->from('glpi_tickets_users as r')
                ->whereColumn('r.tickets_id', 't.id')->where('r.type', 1)->where('r.users_id', $solicitanteId))
            ->selectRaw('tu.users_id, count(distinct t.id) n')->pluck('n', 'users_id');

        $res = [];
        foreach ($porCategoria->keys()->merge($porSolicitante->keys())->unique() as $uid) {
            $res[(int) $uid] = [
                'categoria'   => (int) ($porCategoria[$uid] ?? 0),
                'solicitante' => (int) ($porSolicitante[$uid] ?? 0),
            ];
        }

        return $res;
    }

    /** Vínculos de técnico vigentes de un ticket (glpi_tickets_users), para el control de concurrencia. */
    public function estado(int $id): ?array
    {
        $t = $this->db()->table('glpi_tickets')->where('id', $id)->where('is_deleted', 0)->first(['id', 'status']);
        if (!$t) return null;

        $tecnicos = $this->actores([$id])[$id]['tecnicos'] ?? [];

        return ['estado' => (int) $t->status, 'tecnicos' => $tecnicos];
    }

    /* ── Utilidades ───────────────────────────────────────────────────────── */

    /**
     * El contenido de GLPI es HTML. Se muestra como TEXTO (sin interpretar
     * etiquetas), así nada de lo que escriba un usuario se ejecuta en VTI; los
     * saltos de párrafo se conservan como saltos de línea.
     */
    public static function textoPlano(?string $html, bool $unaLinea = true): string
    {
        $html  = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html  = preg_replace('#<(br|/p|/div|/li|/tr|/h\d)\b[^>]*>#i', "\n", $html);
        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = str_replace("\u{00A0}", ' ', $texto);

        if ($unaLinea) return trim(preg_replace('/\s+/u', ' ', $texto));

        $texto = preg_replace("/[ \t]+/u", ' ', $texto);
        return trim(preg_replace("/\n\s*\n\s*\n+/u", "\n\n", $texto));
    }

    /**
     * País de una ubicación de GLPI: la raíz del árbol, que es «Chile» o
     * «Perú» («Chile > Plantas > Planta Rapel» → «Chile»).
     */
    public static function pais(?string $ubicacion): ?string
    {
        $raiz = trim(Str::before((string) $ubicacion, ' > '));

        return $raiz !== '' ? $raiz : null;
    }

    /** «2 d», «5 h», «20 min»: cuánto lleva esperando. */
    private static function antiguedad(Carbon $desde, Carbon $ahora): string
    {
        $min = (int) $desde->diffInMinutes($ahora);

        return match (true) {
            $min >= 1440 => intdiv($min, 1440) . ' d',
            $min >= 60   => intdiv($min, 60) . ' h',
            default      => max($min, 1) . ' min',
        };
    }

    private static function titulo(?string $t): string
    {
        return mb_convert_case(mb_strtolower(trim((string) $t)), MB_CASE_TITLE, 'UTF-8');
    }

    private static function nombre(object $u): string
    {
        $n = trim(self::titulo($u->firstname ?? '') . ' ' . self::titulo($u->realname ?? ''));

        return $n !== '' ? $n : Str::before((string) $u->name, '@');
    }

    private static function nombreCorto(object $u): string
    {
        $n = trim(Str::before(self::titulo($u->firstname ?? '') . ' ', ' ') . ' ' . Str::before(self::titulo($u->realname ?? '') . ' ', ' '));

        return $n !== '' ? $n : Str::before((string) $u->name, '@');
    }

    private static function iniciales(object $u): string
    {
        $partes = array_filter([trim((string) ($u->firstname ?? '')), trim((string) ($u->realname ?? ''))]) ?: [Str::before((string) $u->name, '@')];

        return Str::upper(implode('', array_map(fn($p) => mb_substr(Str::ascii($p), 0, 1), $partes)));
    }
}
