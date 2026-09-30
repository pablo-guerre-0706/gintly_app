@extends('layouts.panel')

@section('document-title', 'Inteligencia y control')
@section('page-title', 'Inteligencia y control')
@section('breadcrumb-root', 'Panel')
@section('breadcrumb-current', 'Inteligencia y control')
@section('page-script', 'hubs/index')

@section('content')
    <x-panel.hub-page
        title="Inteligencia y control"
        description="Centraliza indicadores, conciliaciones y señales de control respaldadas por el negocio."
        :items="[
            ['title' => 'KPIs y metas', 'description' => 'Indicadores directivos y metas configuradas.', 'icon' => 'fa-bullseye', 'capabilities' => ['metas.ver'], 'url' => route('dashboard')],
            ['title' => 'Reportes', 'description' => 'Consultas analíticas autorizadas para el negocio.', 'icon' => 'fa-chart-column', 'capabilities' => ['reportes.ver'], 'url' => route('panel.sales.summary')],
            ['title' => 'Conciliaciones', 'description' => 'Las corridas de conciliación disponen de contrato API, pero aún no tienen una vista directiva dedicada.', 'icon' => 'fa-code-compare', 'capabilities' => ['conciliacion.ver'], 'status' => 'Vista directiva pendiente'],
            ['title' => 'Centro de alertas y anomalías', 'description' => 'Seguimiento de anomalías detectadas y su estado.', 'icon' => 'fa-triangle-exclamation', 'capabilities' => ['anomalias.ver'], 'url' => route('panel.anomalies.index')],
            ['title' => 'Auditoría', 'description' => 'Bitácora inmutable de acciones del negocio.', 'icon' => 'fa-shield-halved', 'capabilities' => ['auditoria.ver'], 'url' => route('panel.audit.index')],
        ]"
    />
@endsection
