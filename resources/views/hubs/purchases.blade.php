@extends('layouts.panel')

@section('document-title', 'Compras y proveedores')
@section('page-title', 'Compras y proveedores')
@section('breadcrumb-root', 'Panel')
@section('breadcrumb-current', 'Compras y proveedores')
@section('page-script', 'hubs/index')

@section('content')
    <x-panel.hub-page
        title="Compras y proveedores"
        description="Accede a los procesos de abastecimiento que tu cuenta puede consultar o gestionar."
        note="Explorar proveedores usa una frontera externa separada del directorio interno. No creará proveedores registrados ni aprobará resultados automáticamente."
        :items="[
            ['title' => 'Proveedores registrados', 'description' => 'Directorio interno de proveedores del negocio.', 'icon' => 'fa-building', 'capabilities' => ['proveedores.ver'], 'url' => route('panel.suppliers.index')],
            ['title' => 'Órdenes de compra', 'description' => 'Seguimiento del ciclo de órdenes emitidas.', 'icon' => 'fa-file-circle-check', 'capabilities' => ['compras.ver']],
            ['title' => 'Recepciones', 'description' => 'Registro operativo de mercancía recibida.', 'icon' => 'fa-dolly', 'capabilities' => ['compras.recibir']],
            ['title' => 'Explorar proveedores', 'description' => 'Proveedores potenciales externos, separados del directorio interno.', 'icon' => 'fa-map-location-dot', 'roles' => ['ROL-01', 'ROL-02'], 'badge' => 'Externo', 'url' => route('panel.suppliers.explore')],
        ]"
    />
@endsection
