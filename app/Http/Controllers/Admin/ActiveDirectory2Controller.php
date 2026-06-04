<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use LdapRecord\Models\ActiveDirectory\User as AdUser;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ActiveDirectory2Controller extends Controller
{
    protected string $connection = 'secondary';
    protected string $dominio    = 'Grupo Verfrut (Perú)';

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        try {
            $buscar       = trim($request->input('buscar', ''));
            $filtroEstado = $request->input('estado', 'habilitados');
            $perPage      = 40;
            $page         = (int) $request->input('page', 1);

            $query = AdUser::on($this->connection)->select([
                'cn', 'givenname', 'sn', 'samaccountname',
                'mail', 'telephonenumber', 'mobile',
                'department', 'title', 'description',
                'useraccountcontrol', 'whencreated', 'distinguishedname',
            ]);

            if ($buscar !== '') {
                $safe = ldap_escape($buscar, '', LDAP_ESCAPE_FILTER);
                $query->rawFilter(
                    "(|(cn=*{$safe}*)(givenName=*{$safe}*)(sn=*{$safe}*)" .
                    "(mail=*{$safe}*)(sAMAccountName=*{$safe}*)" .
                    "(department=*{$safe}*)(title=*{$safe}*))"
                );
            }

            $todos = $query->orderBy('cn')->get();

            $countHabilitados    = $todos->filter(fn($u) => !(((int)$u->getFirstAttribute('useraccountcontrol')) & 2))->count();
            $countDeshabilitados = $todos->filter(fn($u) =>   (((int)$u->getFirstAttribute('useraccountcontrol')) & 2))->count();
            $countTodos          = $todos->count();

            if ($filtroEstado === 'habilitados') {
                $todos = $todos->filter(fn($u) => !(((int)$u->getFirstAttribute('useraccountcontrol')) & 2));
            } elseif ($filtroEstado === 'deshabilitados') {
                $todos = $todos->filter(fn($u) =>   (((int)$u->getFirstAttribute('useraccountcontrol')) & 2));
            }

            $todos  = $todos->values();
            $total  = $todos->count();
            $slice  = $todos->slice(($page - 1) * $perPage, $perPage)->values();

            $usuarios = new LengthAwarePaginator($slice, $total, $perPage, $page, [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]);

            return view('admin.active_directory2.index', compact(
                'usuarios', 'buscar', 'filtroEstado', 'total',
                'countHabilitados', 'countDeshabilitados', 'countTodos'
            ));

        } catch (\Throwable $e) {
            return view('admin.active_directory2.index', [
                'usuarios'            => null,
                'buscar'              => $request->input('buscar', ''),
                'filtroEstado'        => $request->input('estado', 'habilitados'),
                'total'               => 0,
                'countHabilitados'    => 0,
                'countDeshabilitados' => 0,
                'countTodos'          => 0,
                'ldapError'           => $this->mensajeError($e),
            ]);
        }
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function edit(string $username)
    {
        try {
            $usuario   = AdUser::on($this->connection)->where('samaccountname', $username)->firstOrFail();
            $returnUrl = url()->previous(route('admin.active_directory2.index'));
            return view('admin.active_directory2.edit', compact('usuario', 'returnUrl'));
        } catch (\LdapRecord\Models\ModelNotFoundException) {
            return redirect()->route('admin.active_directory2.index')
                ->withErrors(['Usuario no encontrado en Active Directory.']);
        } catch (\Throwable $e) {
            return redirect()->route('admin.active_directory2.index')
                ->withErrors([$this->mensajeError($e)]);
        }
    }

    // ── Guardar cambios ───────────────────────────────────────────────────────

    public function update(Request $request, string $username)
    {
        $request->validate([
            'givenname'       => 'required|string|max:100',
            'sn'              => 'required|string|max:100',
            'mail'            => 'nullable|email|max:200',
            'telephonenumber' => 'nullable|string|max:50',
            'mobile'          => 'nullable|string|max:50',
            'department'      => 'nullable|string|max:100',
            'title'           => 'nullable|string|max:100',
            'description'     => 'nullable|string|max:500',
        ], [
            'givenname.required' => 'El nombre es obligatorio.',
            'sn.required'        => 'El apellido es obligatorio.',
            'mail.email'         => 'Ingresa un correo válido.',
        ]);

        try {
            $usuario = AdUser::on($this->connection)->where('samaccountname', $username)->firstOrFail();

            $usuario->givenname       = $request->input('givenname');
            $usuario->sn              = $request->input('sn');
            $usuario->displayname     = trim($request->input('givenname') . ' ' . $request->input('sn'));
            $usuario->mail            = $request->input('mail')            ?: null;
            $usuario->telephonenumber = $request->input('telephonenumber') ?: null;
            $usuario->mobile          = $request->input('mobile')          ?: null;
            $usuario->department      = $request->input('department')      ?: null;
            $usuario->title           = $request->input('title')           ?: null;
            $usuario->description     = $request->input('description')     ?: null;

            $usuario->save();

            $returnUrl = $request->input('_return', route('admin.active_directory2.index'));
            return redirect($returnUrl)
                ->with('success', "Usuario {$username} actualizado en Active Directory ({$this->dominio}).");

        } catch (\LdapRecord\Models\ModelNotFoundException) {
            return back()->withErrors(['Usuario no encontrado.']);
        } catch (\Throwable $e) {
            return back()->withErrors([$this->mensajeError($e)])->withInput();
        }
    }

    // ── Habilitar / Deshabilitar ──────────────────────────────────────────────

    public function toggleEnabled(string $username)
    {
        try {
            $usuario    = AdUser::on($this->connection)->where('samaccountname', $username)->firstOrFail();
            $uac        = (int) $usuario->getFirstAttribute('useraccountcontrol');
            $estaActiva = !($uac & 2);

            if ($estaActiva) {
                $usuario->useraccountcontrol = $uac | 2;
                $msg = "Cuenta {$username} deshabilitada correctamente.";
            } else {
                $usuario->useraccountcontrol = $uac & ~2;
                $msg = "Cuenta {$username} habilitada correctamente.";
            }
            $usuario->save();
            return back()->with('success', $msg);

        } catch (\Throwable $e) {
            return back()->withErrors([$this->mensajeError($e)]);
        }
    }

    // ── Resetear contraseña ───────────────────────────────────────────────────

    public function resetPassword(Request $request, string $username)
    {
        $request->validate([
            'nueva_password' => 'required|string|min:8|confirmed',
        ], [
            'nueva_password.required'  => 'La nueva contraseña es obligatoria.',
            'nueva_password.min'       => 'Mínimo 8 caracteres.',
            'nueva_password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        try {
            $usuario = AdUser::on($this->connection)->where('samaccountname', $username)->firstOrFail();
            $usuario->unicodepwd = $request->input('nueva_password');
            $usuario->save();
            return back()->with('success', "Contraseña de {$username} restablecida correctamente.");

        } catch (\LdapRecord\Exceptions\InsufficientAccessException) {
            return back()->withErrors(['La cuenta de servicio no tiene permisos para resetear contraseñas.']);
        } catch (\Throwable $e) {
            $msg = str_contains($e->getMessage(), 'unwilling')
                ? 'AD requiere conexión LDAPS (puerto 636) para cambiar contraseñas.'
                : $this->mensajeError($e);
            return back()->withErrors([$msg]);
        }
    }

    // ── Importar correos desde Excel ──────────────────────────────────────────

    public function importarCorreos()
    {
        return view('admin.active_directory2.importar_correos');
    }

    public function procesarImportacion(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ], [
            'archivo.required' => 'Selecciona un archivo Excel.',
            'archivo.mimes'    => 'Solo se permiten archivos xlsx, xls o csv.',
            'archivo.max'      => 'El archivo no puede superar los 10 MB.',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('archivo')->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);

            $resultados    = [];
            $actualizados  = 0;
            $noEncontrados = 0;
            $errores       = 0;
            $sinCambio     = 0;

            foreach (array_slice($rows, 1) as $i => $row) {
                $correoActual = trim(strtolower((string)($row[0] ?? '')));
                $correoNuevo  = trim((string)($row[1] ?? ''));

                if ($correoActual === '') continue;

                if ($correoNuevo === '') {
                    $resultados[] = ['fila' => $i + 2, 'correo_actual' => $correoActual,
                        'correo_nuevo' => '(vacío)', 'estado' => 'error',
                        'mensaje' => 'La columna B (correo nuevo) está vacía.', 'usuario' => null];
                    $errores++;
                    continue;
                }

                if (strtolower($correoActual) === strtolower($correoNuevo)) {
                    $resultados[] = ['fila' => $i + 2, 'correo_actual' => $correoActual,
                        'correo_nuevo' => $correoNuevo, 'estado' => 'sin_cambio',
                        'mensaje' => 'El correo es idéntico, sin cambios.', 'usuario' => null];
                    $sinCambio++;
                    continue;
                }

                try {
                    $safe    = ldap_escape($correoActual, '', LDAP_ESCAPE_FILTER);
                    $usuario = AdUser::on($this->connection)->rawFilter("(mail={$safe})")->first();

                    if (!$usuario) {
                        $resultados[] = ['fila' => $i + 2, 'correo_actual' => $correoActual,
                            'correo_nuevo' => $correoNuevo, 'estado' => 'no_encontrado',
                            'mensaje' => 'No se encontró ningún usuario AD con ese correo.', 'usuario' => null];
                        $noEncontrados++;
                        continue;
                    }

                    $sam           = $usuario->getFirstAttribute('samaccountname');
                    $usuario->mail = $correoNuevo;
                    $usuario->save();

                    $resultados[] = ['fila' => $i + 2, 'correo_actual' => $correoActual,
                        'correo_nuevo' => $correoNuevo, 'estado' => 'actualizado',
                        'mensaje' => null, 'usuario' => $sam];
                    $actualizados++;

                } catch (\Throwable $e) {
                    $resultados[] = ['fila' => $i + 2, 'correo_actual' => $correoActual,
                        'correo_nuevo' => $correoNuevo, 'estado' => 'error',
                        'mensaje' => $this->mensajeError($e), 'usuario' => null];
                    $errores++;
                }
            }

            $resumen = compact('actualizados', 'noEncontrados', 'errores', 'sinCambio');
            return view('admin.active_directory2.importar_correos', compact('resultados', 'resumen'));

        } catch (\Throwable $e) {
            return back()->withErrors(['Error al leer el archivo: ' . $e->getMessage()]);
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function mensajeError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Can\'t contact LDAP server') || str_contains($msg, 'connection refused')) {
            return 'No se puede conectar al servidor AD (' . $this->dominio . '). Verifica la configuración en Admin → Configuración.';
        }
        if (str_contains($msg, 'Invalid credentials') || str_contains($msg, '80090308')) {
            return 'Credenciales incorrectas para ' . $this->dominio . '. Revisa usuario y contraseña en Admin → Configuración.';
        }
        if (str_contains($msg, 'ldap_bind') || str_contains($msg, 'No credentials')) {
            return 'LDAP (' . $this->dominio . ') no configurado. Completa los datos en Admin → Configuración → AD Perú.';
        }
        if (str_contains($msg, 'No connections exist')) {
            return 'Conexión secundaria no configurada. Completa los datos en Admin → Configuración → AD Perú.';
        }

        return 'Error AD (' . $this->dominio . '): ' . $msg;
    }
}
