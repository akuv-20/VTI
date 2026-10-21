{{-- Modal "Generar Acta de Entrega" reutilizado desde el módulo de equipos --}}
@include('actas_entrega_telefono._modal_generar', [
    'tipo'        => 'entrega',
    'titulo'      => 'Generar Acta de Entrega',
    'buscarUrl'   => route('actas_entrega_telefono.buscar_lineas'),
    'storeBase'   => route('actas_entrega_telefono.store', ['linea' => '__ID__']),
    'btnClass'    => 'btn-success',
    'textClass'   => 'text-success',
    'icono'       => 'bi bi-file-earmark-text',
    'condDefault' => 'Nuevo',
    'accLabel'    => 'Accesorios entregados',
])
