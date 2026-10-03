@extends('layouts.panel')

@section('document-title', 'Registrar conteo físico')
@section('page-title', 'Registrar conteo físico')
@section('breadcrumb-root', 'Inventario y bodega')
@section('breadcrumb-current', 'Registrar conteo')
@section('page-script', 'operations/physical-count')

@section('content')
<section class="mx-auto max-w-3xl space-y-6" data-physical-count-page aria-labelledby="physical-count-title" aria-busy="true">
    <header>
        <p class="text-sm font-semibold text-gintly-brand">Sucursal asignada</p>
        <h1 id="physical-count-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Registrar conteo físico</h1>
        <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Captura la existencia observada. El Backend determina la cantidad del sistema y la diferencia.</p>
    </header>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-physical-count-loading>Cargando bodegas y productos autorizados…</div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800" data-physical-count-fatal role="alert" hidden>
        <p data-physical-count-fatal-message></p>
        <button type="button" class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold" data-physical-count-retry>Reintentar</button>
    </div>

    <form class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-physical-count-form novalidate hidden>
        <div>
            <label class="text-sm font-semibold text-gintly-text-primary" for="physical-count-warehouse">Bodega</label>
            <select id="physical-count-warehouse" name="warehouse_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" required></select>
            <p class="mt-1 text-sm text-red-700" data-field-error="warehouse_id"></p>
        </div>
        <div>
            <label class="text-sm font-semibold text-gintly-text-primary" for="physical-count-product-search">Buscar producto</label>
            <input id="physical-count-product-search" type="search" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="Nombre o SKU" autocomplete="off" data-product-search>
            <label class="mt-4 block text-sm font-semibold text-gintly-text-primary" for="physical-count-product">Producto</label>
            <select id="physical-count-product" name="product_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" required></select>
            <p class="mt-1 text-sm text-red-700" data-field-error="product_id"></p>
        </div>
        <div>
            <label class="text-sm font-semibold text-gintly-text-primary" for="physical-count-quantity">Cantidad contada</label>
            <input id="physical-count-quantity" name="counted_quantity" type="text" inputmode="decimal" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="0.000" required>
            <p class="mt-1 text-sm text-red-700" data-field-error="counted_quantity"></p>
        </div>
        <div>
            <label class="text-sm font-semibold text-gintly-text-primary" for="physical-count-notes">Observaciones</label>
            <textarea id="physical-count-notes" name="notes" rows="4" maxlength="500" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea>
            <p class="mt-1 text-sm text-red-700" data-field-error="notes"></p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-sm text-gintly-text-secondary" data-physical-count-result role="status" tabindex="-1" hidden></div>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:cursor-wait disabled:opacity-60" data-physical-count-submit>Registrar conteo</button>
    </form>
</section>
@endsection
