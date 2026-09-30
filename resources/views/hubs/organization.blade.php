@extends('layouts.panel')

@section('document-title', 'Personal y organización')
@section('page-title', 'Personal y organización')
@section('breadcrumb-root', 'Panel')
@section('breadcrumb-current', 'Personal y organización')
@section('page-script', 'hubs/index')

@section('content')
    <x-panel.hub-page
        title="Personal y organización"
        description="Administra usuarios humanos, perfiles operativos combinables y sucursales del negocio."
        :items="[
            ['title' => 'Usuarios', 'description' => 'Consulta usuarios y administra sus accesos según tu rango.', 'icon' => 'fa-user-gear', 'capabilities' => ['usuarios.ver'], 'url' => route('panel.users.index')],
            ['title' => 'Perfiles operativos ROL-03', 'description' => 'Asigna capacidades de cajero, facturador, bodeguero y despachador.', 'icon' => 'fa-id-badge', 'capabilities' => ['usuarios.gestionar'], 'url' => route('panel.profiles.index')],
            ['title' => 'Sucursales', 'description' => 'Consulta las sedes disponibles dentro del negocio.', 'icon' => 'fa-code-branch', 'capabilities' => ['sucursales.gestionar'], 'url' => route('panel.branches.index')],
        ]"
    />
@endsection
