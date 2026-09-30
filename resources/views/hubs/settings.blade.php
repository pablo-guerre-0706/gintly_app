@extends('layouts.panel')

@section('document-title', 'Configuración')
@section('page-title', 'Configuración')
@section('breadcrumb-root', 'Panel')
@section('breadcrumb-current', 'Configuración')
@section('page-script', 'hubs/index')

@section('content')
    <x-panel.hub-page
        title="Configuración"
        description="Consulta las áreas configurables del negocio que están habilitadas para tu cuenta."
        :items="[
            ['title' => 'Datos del negocio', 'description' => 'Identidad y configuración operativa general.', 'icon' => 'fa-store', 'capabilities' => ['negocio.ver']],
            ['title' => 'Reglas fiscales', 'description' => 'Parámetros fiscales aplicables a la operación.', 'icon' => 'fa-percent', 'roles' => ['ROL-01']],
            ['title' => 'Reglas de anomalías', 'description' => 'Criterios que originan alertas de control.', 'icon' => 'fa-sliders', 'capabilities' => ['reglas_anomalia.ver']],
        ]"
    />
@endsection
