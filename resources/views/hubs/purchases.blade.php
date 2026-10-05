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
        note="El mapa muestra proveedores aprobados y activos con ubicaciones confirmadas. La exploración de negocios externos no está conectada."
        :items="[
            ['title' => 'Proveedores registrados', 'description' => 'Directorio interno de proveedores del negocio.', 'icon' => 'fa-building', 'capabilities' => ['proveedores.ver'], 'url' => route('panel.suppliers.index')],
            ['title' => 'Órdenes de compra', 'description' => 'Supervisión del ciclo de órdenes emitidas.', 'icon' => 'fa-file-circle-check', 'capabilities' => ['compras.ver'], 'roles' => ['ROL-02'], 'url' => route('panel.admin.purchase-orders')],
            ['title' => 'Recepciones', 'description' => 'Consulta administrativa de recepciones y discrepancias.', 'icon' => 'fa-dolly', 'capabilities' => ['compras.ver'], 'roles' => ['ROL-02'], 'url' => route('panel.admin.goods-receipts')],
            ['title' => 'Mapa de proveedores', 'description' => 'Ubicaciones confirmadas de los proveedores del negocio.', 'icon' => 'fa-map-location-dot', 'capabilities' => ['proveedores.ver'], 'roles' => ['ROL-01', 'ROL-02'], 'url' => route('panel.suppliers.explore')],
        ]"
    />
@endsection
