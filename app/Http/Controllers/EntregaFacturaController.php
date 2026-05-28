<?php

namespace App\Http\Controllers;

use App\Models\EntregaFactura;
use App\Models\EntregaFacturaItem;
use App\Models\Factura;
use App\Models\Compania;
use Illuminate\Http\Request;

class EntregaFacturaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $buscar = $request->input('buscar');

        $query = EntregaFactura::with('usuario')
            ->withCount('items')
            ->latest();

        if ($buscar) {
            $query->whereHas('items.factura', fn($q) =>
                $q->where('factura', 'like', "%{$buscar}%")
            );
        }

        $entregas = $query->paginate(20)->withQueryString();

        return view('entregas_facturas.index', compact('entregas', 'buscar'));
    }

    public function create()
    {
        return view('entregas_facturas.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'facturas'    => 'required|array|min:1',
            'facturas.*' => 'integer|exists:facturas,id',
            'observacion' => 'nullable|string|max:500',
        ], [
            'facturas.required' => 'Debe agregar al menos una factura a la entrega.',
            'facturas.min'      => 'Debe agregar al menos una factura a la entrega.',
        ]);

        // Verificar que ninguna factura ya está en otra entrega
        $yaEntregadas = EntregaFacturaItem::whereIn('id_factura', $request->facturas)
            ->with('entrega')
            ->get();

        if ($yaEntregadas->isNotEmpty()) {
            $nums = $yaEntregadas->map(fn($i) => $i->factura->factura ?? $i->id_factura)->implode(', ');
            return back()
                ->withInput()
                ->withErrors(['facturas' => "Las siguientes facturas ya están incluidas en otra entrega: {$nums}"]);
        }

        $entrega = EntregaFactura::create([
            'id_usuario'  => auth()->id(),
            'observacion' => $request->observacion,
        ]);

        $now   = now();
        $items = array_map(fn($id) => [
            'id_entrega' => $entrega->id,
            'id_factura' => $id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $request->facturas);

        EntregaFacturaItem::insert($items);

        return redirect()
            ->route('entregas_facturas.show', $entrega)
            ->with('success', 'Entrega registrada exitosamente.');
    }

    public function show(EntregaFactura $entrega)
    {
        $entrega->load([
            'usuario',
            'items.factura.servicio.compania',
            'items.factura.servicio.cuentaContable',
            'items.factura.cuentaContable',
        ]);

        // Índice nombre→compania para resolver RUT en facturas esporádicas
        $companiasPorNombre = Compania::all()->keyBy('nombre');

        return view('entregas_facturas.show', compact('entrega', 'companiasPorNombre'));
    }

    public function destroy(EntregaFactura $entrega)
    {
        $entrega->delete(); // items cascade
        return redirect()
            ->route('entregas_facturas.index')
            ->with('success', 'Entrega eliminada. Las facturas volvieron a estar disponibles.');
    }

    /** Vista de impresión */
    public function imprimir(EntregaFactura $entrega)
    {
        $entrega->load([
            'usuario',
            'items.factura.servicio.compania',
            'items.factura.servicio.cuentaContable',
            'items.factura.cuentaContable',
        ]);

        $companiasPorNombre = Compania::all()->keyBy('nombre');

        return view('entregas_facturas.print', compact('entrega', 'companiasPorNombre'));
    }

    /** Endpoint AJAX — busca facturas disponibles (sin entrega asignada) */
    public function buscarFacturas(Request $request)
    {
        $q = trim($request->input('q', ''));

        $query = Factura::with(['servicio.compania', 'cuentaContable', 'servicio.cuentaContable'])
            ->doesntHave('entregaItem');

        if (strlen($q) >= 2) {
            $query->where(function ($sub) use ($q) {
                $sub->where('factura', 'like', "%{$q}%")
                    ->orWhere('proveedor', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%")
                    ->orWhereHas('servicio.compania', fn($c) => $c->where('nombre', 'like', "%{$q}%"));
            });
        }

        $facturas = $query->orderByDesc('fecha_emision')->limit(30)->get();

        // Índice para resolver RUT de esporádicas por nombre de compañía
        $companiasPorNombre = Compania::all()->keyBy('nombre');

        return response()->json($facturas->map(function ($f) use ($companiasPorNombre) {
            $tieneServicio = $f->id_servicio && $f->servicio;
            $cc            = $f->cuentaContableEfectiva;

            if ($tieneServicio) {
                $nombreProv = $f->servicio->compania->nombre ?? '—';
                $rutProv    = $f->servicio->compania->rut ?? null;
            } else {
                $nombreProv = $f->proveedor ?? '—';
                $rutProv    = $f->proveedor
                    ? ($companiasPorNombre->get($f->proveedor)?->rut ?? null)
                    : null;
            }

            return [
                'id'               => $f->id,
                'factura'          => $f->factura,
                'proveedor_rut'    => $rutProv,
                'proveedor_nombre' => $nombreProv,
                'descripcion'      => $tieneServicio
                    ? ($f->descripcion ?? $f->servicio->concepto ?? $f->servicio->servicio ?? '—')
                    : ($f->descripcion ?? '—'),
                'cuenta_contable'  => $cc
                    ? $cc->numero_cuenta . ' ' . $cc->nombre_cuenta
                    : '—',
                'valor_neto'       => $f->valor_neto,
                'total'            => $f->total,
                'oc'               => $f->oc,
                'fecha_emision'    => $f->fecha_emision?->format('d/m/Y'),
            ];
        }));
    }
}
