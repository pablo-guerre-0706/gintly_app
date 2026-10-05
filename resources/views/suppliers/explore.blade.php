@extends('layouts.panel')

@section('document-title', 'Mapa de proveedores')
@section('page-title', 'Mapa de proveedores')
@section('breadcrumb-root', 'Compras y proveedores')
@section('breadcrumb-current', 'Mapa de proveedores')
@section('page-script', 'suppliers/explore')

@section('content')
<section class="space-y-6" data-supplier-explorer data-mobile-view="map" aria-labelledby="supplier-explorer-title" aria-busy="true">
    <header class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-gintly-border bg-white p-5 sm:p-7">
        <div class="min-w-0 max-w-2xl"><p class="text-sm font-semibold text-gintly-brand"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i> Red de abastecimiento</p><h1 id="supplier-explorer-title" class="mt-2 text-2xl font-bold sm:text-3xl">Mapa de proveedores</h1><p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Ubicaciones confirmadas de proveedores aprobados y activos de tu negocio. Este mapa no busca ni registra negocios externos automáticamente.</p></div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('panel.suppliers.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold" data-directory-link hidden>Directorio y candidatos</a><button type="button" data-supplier-refresh class="min-h-11 rounded-xl bg-gintly-brand px-4 text-sm font-semibold text-white">Actualizar</button></div>
    </header>
    <form data-map-filters class="grid items-end gap-4 rounded-2xl border border-gintly-border bg-white p-5 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_auto_auto]" role="search" aria-label="Buscar en el mapa de proveedores">
        <label class="min-w-0 text-sm font-semibold sm:col-span-2 xl:col-span-1" for="supplier-map-search">Nombre o dirección<input id="supplier-map-search" type="search" name="search" autocomplete="off" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3" placeholder="Buscar entre los proveedores del mapa"></label>
        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="primary">Solo ubicaciones principales</label>
        <button type="button" data-map-fit class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold" disabled>Encuadrar resultados</button>
    </form>
    <p data-supplier-notice class="rounded-xl bg-slate-50 p-4 text-sm" role="status" hidden></p>
    <p data-map-loading role="status" class="rounded-2xl border border-slate-200 bg-white p-6">Consultando proveedores y ubicaciones autorizadas…</p>
    <div data-map-error role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5" hidden><p data-map-error-message></p><button type="button" data-map-retry class="mt-3 min-h-11 rounded-xl border border-red-300 bg-white px-4 text-sm font-semibold">Reintentar consulta</button></div>
    <div data-map-content class="space-y-4" hidden>
        <p data-map-count class="text-sm text-gintly-text-secondary" role="status"></p>
        <div class="flex gap-2 md:hidden" role="group" aria-label="Presentación de proveedores"><button type="button" data-mobile-map aria-pressed="true" aria-controls="supplier-map-column" class="min-h-11 flex-1 rounded-xl border border-gintly-brand px-4 text-sm font-semibold">Mapa</button><button type="button" data-mobile-list aria-pressed="false" aria-controls="supplier-list-column" class="min-h-11 flex-1 rounded-xl border border-gintly-brand px-4 text-sm font-semibold">Lista</button></div>
        <p data-map-empty class="rounded-xl bg-slate-50 p-5 text-sm" hidden></p>
        <div class="grid min-w-0 gap-5 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            <section id="supplier-map-column" data-map-column class="min-w-0 rounded-2xl border border-gintly-border bg-white p-3" aria-label="Mapa interactivo">
                <div data-map-canvas class="supplier-map-canvas" aria-label="Mapa: flechas para desplazarse, más y menos para zoom"></div>
                <div data-map-tiles-error class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900" role="status" hidden><p>La capa cartográfica no pudo cargarse. Las ubicaciones y el listado siguen disponibles.</p><button type="button" data-tiles-retry class="mt-2 min-h-11 rounded-xl border border-amber-300 px-4 font-semibold">Reintentar capa</button></div>
                <p class="mt-3 text-xs leading-5 text-gintly-text-secondary">La cartografía externa recibe tu IP y la zona visualizada. No enviamos nombres, direcciones ni contactos de proveedores. La vista inicial no representa un proveedor.</p>
            </section>
            <section id="supplier-list-column" data-list-column class="min-w-0 space-y-3" aria-label="Listado sincronizado de proveedores"><div data-map-list class="space-y-3"></div></section>
        </div>
        <section data-map-detail class="rounded-2xl border border-gintly-border bg-white p-5" aria-label="Detalle de ubicación" hidden></section>
    </div>
    @include('suppliers.partials.dialogs')
</section>
@endsection
