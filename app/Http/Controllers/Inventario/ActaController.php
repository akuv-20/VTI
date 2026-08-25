<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\ActaEntregaEquipo;
use App\Models\Configuracion;
use App\Services\DominioInventario;
use App\Services\InventarioGlpi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Actas de entrega de equipos, por dominio.
 *
 * El acta guarda un snapshot completo del equipo, así que sigue siendo válida
 * aunque el equipo cambie o desaparezca del GLPI. Lo que sí importa es el
 * `dominio`: sin él, el `glpi_computer_id` deja de ser único entre bases y el
 * historial de una ficha mostraría actas de otro GLPI.
 */
class ActaController extends BaseController
{
    /** Listado de actas del dominio. */
    public function index()
    {
        $dom = $this->dominio();

        return view('inventario.actas', [
            'dom'   => $dom,
            'actas' => ActaEntregaEquipo::where('dominio', $dom->clave)->latest()->paginate(25),
        ]);
    }

    /** Genera el acta de un equipo. */
    public function store(Request $request, $id)
    {
        $dom = $this->dominio();
        $glpi   = new InventarioGlpi($dom);
        $equipo = $glpi->equipo((int) $id);

        abort_if(!$equipo, 404);

        // No generar acta si faltan datos críticos del equipo en GLPI
        $faltantes = [];
        if (!trim($equipo->nombre_usuario ?? '')) $faltantes[] = 'usuario asignado';
        if (!$equipo->ubicacion)                  $faltantes[] = 'ubicación';
        if ($faltantes) {
            return back()->withErrors([
                'acta' => 'No es posible generar el acta: el equipo no tiene ' . implode(' ni ', $faltantes) . ' en GLPI.',
            ]);
        }

        // Bloqueo: no permitir un acta para el mismo usuario el mismo día.
        // Se acota al dominio: dos personas homónimas en dominios distintos no
        // tienen por qué estorbarse.
        $receptor = trim($equipo->nombre_usuario);
        $existe = ActaEntregaEquipo::where('dominio', $dom->clave)
            ->where('nombre_receptor', $receptor)
            ->whereDate('fecha_emision', now()->toDateString())
            ->exists();
        if ($existe) {
            return back()->withErrors([
                'acta' => "Ya existe un acta de entrega generada hoy para {$receptor}. " .
                          "Edita la existente o genérala el día siguiente.",
            ]);
        }

        $validated = $request->validate([
            'condicion'           => 'required|in:Nuevo,Usado',
            'accesorios.monitor'  => 'nullable|in:SI,NO',
            'accesorios.mouse'    => 'nullable|in:SI,NO',
            'accesorios.teclado'  => 'nullable|in:SI,NO',
            'accesorios.mochila'  => 'nullable|in:SI,NO',
            'observacion'         => 'nullable|string|max:500',
        ]);

        $hardware = $glpi->hardwareDe((int) $equipo->id);

        $acta = ActaEntregaEquipo::create([
            'dominio'           => $dom->clave,
            'glpi_computer_id'  => $equipo->id,
            'fecha_emision'     => now()->toDateString(),
            'nombre_equipo'     => $equipo->nombre_equipo,
            'nombre_receptor'   => $equipo->nombre_usuario ?: null,
            'ubicacion'         => $equipo->ubicacion,
            'marca'             => $equipo->marca,
            'modelo'            => $equipo->modelo,
            'numero_serie'      => $equipo->numero_serie,
            'sistema_operativo' => $equipo->sistema_operativo,
            'procesador'        => $hardware['procesador'],
            'ram'               => $hardware['ram'],
            'disco'             => $hardware['disco'],
            'condicion'         => $validated['condicion'],
            'accesorios'        => $validated['accesorios'] ?? [],
            'observacion'       => $validated['observacion'] ?? null,
            'entregado_por'     => auth()->user()->name,
        ]);

        return redirect()->route("inventario.{$dom->clave}.actas.imprimir", $acta);
    }

    public function imprimir(ActaEntregaEquipo $acta)
    {
        $dom = $this->actaDelDominio($acta);

        $logoPath = Configuracion::get('app_logo');
        $appLogo  = $logoPath ? Storage::url($logoPath) : null;

        return view('inventario.acta_imprimir', compact('acta', 'appLogo', 'dom'));
    }

    public function edit(ActaEntregaEquipo $acta)
    {
        $dom = $this->actaDelDominio($acta);

        if ($acta->bloqueadaParaEdicion()) {
            return redirect()->route("inventario.{$dom->clave}.actas")
                ->with('error', 'El acta no puede editarse: fue emitida hace más de 2 días.');
        }

        return view('inventario.acta_editar', compact('acta', 'dom'));
    }

    public function update(Request $request, ActaEntregaEquipo $acta)
    {
        $dom = $this->actaDelDominio($acta);

        if ($acta->bloqueadaParaEdicion()) {
            return redirect()->route("inventario.{$dom->clave}.actas")
                ->with('error', 'El acta no puede editarse: fue emitida hace más de 2 días.');
        }

        $validated = $request->validate([
            'fecha_emision'       => 'required|date',
            'nombre_equipo'       => 'required|string|max:150',
            'nombre_receptor'     => 'nullable|string|max:150',
            'ubicacion'           => 'nullable|string|max:200',
            'marca'               => 'nullable|string|max:100',
            'modelo'              => 'nullable|string|max:100',
            'numero_serie'        => 'nullable|string|max:100',
            'sistema_operativo'   => 'nullable|string|max:150',
            'procesador'          => 'nullable|string|max:150',
            'ram'                 => 'nullable|string|max:50',
            'disco'               => 'nullable|string|max:50',
            'condicion'           => 'required|in:Nuevo,Usado',
            'accesorios.monitor'  => 'nullable|in:SI,NO',
            'accesorios.mouse'    => 'nullable|in:SI,NO',
            'accesorios.teclado'  => 'nullable|in:SI,NO',
            'accesorios.mochila'  => 'nullable|in:SI,NO',
            'observacion'         => 'nullable|string|max:500',
        ]);

        // El dominio del acta no se toca: es lo que la ata a su GLPI de origen.
        $acta->update([
            'fecha_emision'     => $validated['fecha_emision'],
            'nombre_equipo'     => $validated['nombre_equipo'],
            'nombre_receptor'   => $validated['nombre_receptor'] ?? null,
            'ubicacion'         => $validated['ubicacion'] ?? null,
            'marca'             => $validated['marca'] ?? null,
            'modelo'            => $validated['modelo'] ?? null,
            'numero_serie'      => $validated['numero_serie'] ?? null,
            'sistema_operativo' => $validated['sistema_operativo'] ?? null,
            'procesador'        => $validated['procesador'] ?? null,
            'ram'               => $validated['ram'] ?? null,
            'disco'             => $validated['disco'] ?? null,
            'condicion'         => $validated['condicion'],
            'accesorios'        => $validated['accesorios'] ?? [],
            'observacion'       => $validated['observacion'] ?? null,
        ]);

        return redirect()->route("inventario.{$dom->clave}.actas")
            ->with('success', 'Acta actualizada correctamente.');
    }

    /** Eliminar acta (solo admin). */
    public function destroy(ActaEntregaEquipo $acta)
    {
        $this->actaDelDominio($acta);
        $this->authorize('admin');

        $acta->delete();

        return back()->with('success', 'Acta eliminada.');
    }

    /**
     * Resuelve el dominio y comprueba que el acta le pertenezca.
     *
     * Sin esto, quien tenga permiso sobre un dominio podría abrir por URL las
     * actas del otro: el permiso está en la ruta, pero el id del acta no.
     */
    private function actaDelDominio(ActaEntregaEquipo $acta): DominioInventario
    {
        $dom = $this->dominio();
        abort_unless($acta->dominio === $dom->clave, 404);

        return $dom;
    }
}
