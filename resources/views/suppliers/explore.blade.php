@extends('layouts.panel')

@section('document-title', 'Mapa de proveedores')
@section('page-title', 'Mapa de proveedores')
@section('breadcrumb-root', 'Compras y proveedores')
@section('breadcrumb-current', 'Mapa de proveedores')
@section('page-script', 'suppliers/explore')

@section('content')
<section class="supplier-map-workspace" data-supplier-explorer data-mobile-view="map" aria-labelledby="supplier-explorer-title" aria-busy="true">
    <div class="supplier-map-view-switch" role="group" aria-label="Presentación de proveedores">
        <button type="button" data-mobile-map aria-pressed="true" aria-controls="supplier-map-column" class="min-h-11 flex-1 rounded-xl px-4 text-sm font-semibold"><i class="fa-solid fa-map-location-dot mr-2" aria-hidden="true"></i>Mapa</button>
        <button type="button" data-mobile-list aria-pressed="false" aria-controls="supplier-list-column" class="min-h-11 flex-1 rounded-xl px-4 text-sm font-semibold"><i class="fa-solid fa-list mr-2" aria-hidden="true"></i>Lista de proveedores</button>
    </div>
    <section id="supplier-list-column" data-list-column class="supplier-map-panel bg-white" aria-label="Listado sincronizado de proveedores" tabindex="-1">
        <header>
            <div class="flex items-start justify-between gap-2">
                <h1 id="supplier-explorer-title" class="text-2xl font-bold leading-tight text-gintly-sidebar">Mapa de proveedores</h1>
                <button type="button" data-supplier-refresh class="grid size-11 shrink-0 place-items-center rounded-full border border-slate-200 text-gintly-brand hover:bg-slate-50" aria-label="Actualizar proveedores" title="Actualizar proveedores"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></button>
            </div>
            <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Ubicaciones confirmadas de proveedores aprobados y activos</p>
        </header>
        <form data-map-filters class="mt-5 space-y-3" role="search" aria-label="Buscar en el mapa de proveedores">
            <label class="block text-sm font-semibold" for="supplier-map-search">Nombre o dirección</label>
            <input id="supplier-map-search" type="search" name="search" autocomplete="off" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3 text-sm" placeholder="Buscar proveedores o direcciones">
            <div class="supplier-map-filter-row">
                <label class="flex min-h-11 items-center gap-2 text-xs leading-5"><input type="checkbox" name="primary">Solo ubicaciones principales</label>
                <button type="button" data-map-fit class="min-h-11 rounded-xl border border-slate-300 px-2 text-xs font-semibold" disabled>Encuadrar resultados</button>
            </div>
        </form>
        <h2 class="mt-6 text-base font-bold text-gintly-sidebar">Proveedores aprobados</h2>
        <p data-map-count class="mt-2 text-xs text-gintly-text-secondary" role="status">Consultando proveedores…</p>
        <p data-supplier-notice class="mt-3 rounded-xl bg-slate-50 p-3 text-sm" role="status" hidden></p>
        <p data-map-loading role="status" class="mt-4 rounded-xl bg-slate-50 p-4 text-sm">Consultando proveedores y ubicaciones autorizadas…</p>
        <div data-map-error role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm" hidden><p data-map-error-message></p><button type="button" data-map-retry class="mt-3 min-h-11 rounded-xl border border-red-300 bg-white px-3 font-semibold">Reintentar consulta</button></div>
        <div data-map-content class="mt-4" hidden>
            <p data-map-empty class="rounded-xl bg-slate-50 p-4 text-sm leading-6" hidden></p>
            <div data-map-list class="space-y-3"></div>
        </div>
        <section id="supplier-map-detail" data-map-detail class="mt-3 rounded-xl bg-slate-50 p-3" aria-label="Detalle de ubicación" hidden></section>
        <div class="mt-5 border-t border-slate-200 pt-4">
            <a href="{{ route('panel.suppliers.index') }}" class="mb-3 inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-3 text-sm font-semibold" data-directory-link hidden>Directorio y candidatos</a>
            <p class="text-xs leading-5 text-gintly-text-secondary">La cartografía externa recibe tu IP y la zona visualizada, no datos de proveedores. La vista inicial no representa un proveedor.</p>
        </div>
    </section>
    <section id="supplier-map-column" data-map-column class="supplier-map-column" aria-label="Mapa interactivo">
        <div data-map-canvas class="supplier-map-canvas" aria-label="Mapa: flechas para desplazarse, más y menos para zoom"></div>
        <a href="{{ route('dashboard') }}" class="supplier-map-return grid size-11 place-items-center rounded-full bg-white text-gintly-sidebar shadow-md hover:bg-slate-50" aria-label="Volver al dashboard" title="Volver al dashboard"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
        <div data-map-tiles-error class="supplier-map-tile-notice rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status" hidden><p>La capa cartográfica no pudo cargarse. Las ubicaciones y el listado siguen disponibles.</p><button type="button" data-tiles-retry class="mt-2 min-h-11 rounded-xl border border-amber-300 px-3 font-semibold">Reintentar capa</button></div>
    </section>
    @include('suppliers.partials.dialogs')
</section>
@endsection
