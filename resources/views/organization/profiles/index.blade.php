@extends('layouts.panel')

@section('document-title', 'Perfiles operativos')
@section('page-title', 'Perfiles operativos ROL-03')
@section('breadcrumb-root', 'Personal y organización')
@section('breadcrumb-current', 'Perfiles operativos')
@section('page-script', 'hubs/index')

@section('content')
    <x-panel.hub-page
        title="Perfiles operativos ROL-03"
        description="Los perfiles son combinables y se asignan desde el acceso de cada usuario operativo."
        note="Cajero, facturador, bodeguero y despachador son perfiles funcionales dentro de ROL-03; no son roles independientes."
        :items="[
            ['title' => 'Administrar perfiles por usuario', 'description' => 'Localiza el usuario y abre su configuración de acceso para consultar o reemplazar sus perfiles.', 'icon' => 'fa-user-shield', 'capabilities' => ['usuarios.gestionar'], 'url' => route('panel.users.index')],
        ]"
    />
@endsection
