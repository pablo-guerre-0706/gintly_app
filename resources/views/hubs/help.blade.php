@extends('layouts.panel')

@section('document-title', 'Centro de ayuda')
@section('page-title', 'Centro de ayuda')
@section('breadcrumb-root', 'Panel')
@section('breadcrumb-current', 'Centro de ayuda')
@section('page-script', 'hubs/index')

@section('content')
    <x-panel.hub-page
        title="Centro de ayuda"
        description="Orientación local para utilizar el panel sin publicar canales de soporte que todavía no tienen un contrato confirmado."
        :items="[
            ['title' => 'Navegación del panel', 'description' => 'Usa el buscador del encabezado o los grupos del sidebar para abrir únicamente destinos habilitados.', 'icon' => 'fa-compass', 'roles' => ['ROL-01', 'ROL-02', 'ROL-03'], 'status' => 'Guía disponible'],
            ['title' => 'Acceso y sesión', 'description' => 'El panel protege la sesión mediante cookies, CSRF y el cierre de sesión canónico.', 'icon' => 'fa-shield-halved', 'roles' => ['ROL-01', 'ROL-02', 'ROL-03'], 'status' => 'Información disponible'],
        ]"
    />
@endsection
