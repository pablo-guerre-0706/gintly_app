@extends('layouts.panel')

@section('document-title', 'Finanzas y créditos')
@section('page-title', 'Finanzas y créditos')
@section('breadcrumb-root', 'Panel')
@section('breadcrumb-current', 'Finanzas y créditos')
@section('page-script', 'hubs/index')

@section('content')
    <x-panel.hub-page
        title="Finanzas y créditos"
        description="Consulta el estado financiero consolidado sin ejecutar tareas operativas de caja."
        :items="[
            ['title' => 'Estado de cajas', 'description' => 'Cierres y diferencias agregadas del período.', 'icon' => 'fa-cash-register', 'capabilities' => ['reportes.ver'], 'roles' => ['ROL-01'], 'url' => route('panel.finance.cash-overview')],
            ['title' => 'Cuentas por cobrar', 'description' => 'Exposición, recuperación y cartera vencida.', 'icon' => 'fa-hand-holding-dollar', 'capabilities' => ['reportes.ver'], 'roles' => ['ROL-01'], 'url' => route('panel.finance.receivables')],
            ['title' => 'Cuentas por pagar', 'description' => 'Obligaciones vigentes con proveedores registrados.', 'icon' => 'fa-file-invoice', 'capabilities' => ['cuentas_por_pagar.ver'], 'url' => route('panel.finance.payables')],
            ['title' => 'Cajas registradoras', 'description' => 'Catálogo de cajas y asignación por sucursal.', 'icon' => 'fa-cash-register', 'capabilities' => ['caja.gestionar'], 'roles' => ['ROL-02'], 'url' => route('panel.admin.cash-registers')],
            ['title' => 'Sesiones y cierres', 'description' => 'Supervisión y cierre administrativo de contingencia.', 'icon' => 'fa-vault', 'capabilities' => ['caja.gestionar'], 'roles' => ['ROL-02'], 'url' => route('panel.admin.cash-sessions')],
        ]"
    />
@endsection
