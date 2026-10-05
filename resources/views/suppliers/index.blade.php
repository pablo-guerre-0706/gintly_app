@extends('layouts.panel')
@section('document-title', 'Proveedores registrados')
@section('page-title', 'Proveedores registrados')
@section('breadcrumb-root', 'Compras y proveedores')
@section('page-script', 'suppliers/index')
@section('content')
<section data-supplier-directory class="space-y-6" aria-labelledby="supplier-directory-title" aria-busy="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="max-w-2xl"><p class="text-sm font-semibold text-gintly-brand">Directorio del negocio</p><h1 id="supplier-directory-title" class="mt-2 text-2xl font-bold sm:text-3xl">Proveedores registrados</h1><p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Candidatos, proveedores aprobados y suspendidos. La aprobación y la confirmación de ubicaciones se gestionan por separado.</p></div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('panel.suppliers.explore') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold">Ver mapa</a><button data-supplier-refresh type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Actualizar</button><button data-candidate-create type="button" class="min-h-11 rounded-xl bg-gintly-brand px-4 text-sm font-semibold text-white" hidden>Registrar candidato</button></div>
    </header>
    <form data-directory-filters class="grid gap-4 rounded-2xl border border-gintly-border bg-white p-5 sm:grid-cols-2 xl:grid-cols-4">
        <label class="text-sm font-semibold">Nombre o identificación fiscal<input name="search" type="search" minlength="2" maxlength="160" autocomplete="off" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3"></label>
        <label class="text-sm font-semibold">Estado<select name="status" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3"><option value="">Todos</option><option value="pendiente">Pendiente</option><option value="aprobado">Aprobado</option><option value="suspendido">Suspendido</option></select></label>
        <label class="text-sm font-semibold">Disponibilidad<select name="is_active" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select></label>
        <button type="submit" class="min-h-11 self-end rounded-xl bg-gintly-brand px-4 font-semibold text-white">Aplicar filtros</button>
    </form>
    <p data-supplier-notice class="rounded-xl bg-slate-50 p-4 text-sm" role="status" hidden></p>
    <p data-directory-loading role="status" class="rounded-2xl bg-white p-6">Consultando directorio…</p>
    <div data-directory-error class="rounded-2xl border border-red-200 bg-red-50 p-5" role="alert" hidden><p data-directory-error-message></p><button data-directory-retry type="button" class="mt-3 min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold">Reintentar</button></div>
    <p data-directory-empty class="rounded-2xl bg-slate-50 p-6 text-sm" hidden>No hay proveedores para estos filtros.</p>
    <div data-directory-records class="grid gap-4 lg:grid-cols-2" hidden></div>
    <nav data-directory-pagination class="flex flex-wrap items-center justify-between gap-3" aria-label="Páginas del directorio" hidden><p data-directory-page class="text-sm"></p><div class="flex gap-2"><button data-directory-prev type="button" class="min-h-11 rounded-xl border border-slate-300 px-4">Anterior</button><button data-directory-next type="button" class="min-h-11 rounded-xl border border-slate-300 px-4">Siguiente</button></div></nav>
    @include('suppliers.partials.dialogs')
</section>
@endsection
