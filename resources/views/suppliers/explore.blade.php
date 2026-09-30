@extends('layouts.panel')

@section('document-title', 'Mapa de proveedores potenciales')
@section('page-title', 'Explorar proveedores')
@section('breadcrumb-root', 'Compras y proveedores')
@section('breadcrumb-current', 'Explorar proveedores')
@section('page-script', 'suppliers/explore')

@section('content')
<section class="space-y-6" data-supplier-explorer aria-labelledby="supplier-explorer-title">
    <header class="rounded-3xl border border-gintly-border bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
            <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-gintly-brand/10 text-2xl text-gintly-brand" aria-hidden="true">
                <i class="fa-solid fa-map-location-dot"></i>
            </span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Fuente externa</p>
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-900">Externo</span>
                </div>
                <h1 id="supplier-explorer-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Mapa de proveedores potenciales</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary sm:text-base">
                    Esta herramienta permitirá localizar negocios cercanos como posibles fuentes de productos o servicios. Los resultados no serán proveedores registrados ni quedarán aprobados automáticamente.
                </p>
            </div>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,.6fr)]">
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6" data-map-status role="status" aria-live="polite">
            <h2 class="text-lg font-bold text-amber-950">Proveedor cartográfico pendiente de configuración</h2>
            <p class="mt-3 text-sm leading-6 text-amber-900" data-map-status-message>
                La búsqueda geográfica no está disponible todavía. Debe aprobarse un proveedor, su política de costos y una clave restringida antes de activar el mapa.
            </p>
        </section>

        <aside class="rounded-2xl border border-gintly-border bg-white p-6 shadow-sm" aria-labelledby="supplier-boundary-title">
            <h2 id="supplier-boundary-title" class="font-bold text-gintly-text-primary">Separación con proveedores internos</h2>
            <ul class="mt-4 space-y-3 text-sm leading-6 text-gintly-text-secondary">
                <li class="flex gap-3"><i class="fa-solid fa-circle-check mt-1 text-gintly-brand" aria-hidden="true"></i><span>Los resultados provendrán de una fuente externa.</span></li>
                <li class="flex gap-3"><i class="fa-solid fa-circle-check mt-1 text-gintly-brand" aria-hidden="true"></i><span>La geolocalización requerirá consentimiento explícito.</span></li>
                <li class="flex gap-3"><i class="fa-solid fa-circle-check mt-1 text-gintly-brand" aria-hidden="true"></i><span>Registrar y aprobar un proveedor seguirá siendo un proceso independiente.</span></li>
            </ul>
        </aside>
    </div>
</section>
@endsection
