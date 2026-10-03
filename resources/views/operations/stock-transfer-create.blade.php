@extends('layouts.panel')

@section('document-title', 'Nuevo traspaso')
@section('page-title', 'Nuevo traspaso')
@section('breadcrumb-root', 'Inventario y bodega')
@section('breadcrumb-current', 'Nuevo traspaso')
@section('page-script', 'operations/stock-transfer-create')

@section('content')
<section class="mx-auto max-w-4xl space-y-6" data-stock-transfer-create aria-labelledby="transfer-title" aria-busy="true">
    <header>
        <p class="text-sm font-semibold text-gintly-brand">Sucursal asignada</p>
        <h1 id="transfer-title" class="mt-2 text-2xl font-bold text-gintly-text-primary sm:text-3xl">Nuevo traspaso</h1>
        <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Selecciona una bodega de origen de tu sucursal, un destino autorizado y productos con existencias disponibles. La confirmación de recepción corresponde a la sucursal destino.</p>
    </header>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-operation-loading role="status">Cargando bodegas y existencias…</div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800" data-operation-fatal role="alert" hidden>
        <p data-operation-fatal-message></p>
        <button type="button" class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold" data-operation-retry>Reintentar</button>
    </div>

    <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-operation-form novalidate hidden>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="transfer-origin" class="block text-sm font-semibold">Bodega origen</label>
                <select id="transfer-origin" name="from_warehouse_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm" required></select>
                <p class="mt-1 text-sm text-red-700" data-field-error="from_warehouse_id"></p>
            </div>
            <div>
                <label for="transfer-destination" class="block text-sm font-semibold">Bodega destino</label>
                <select id="transfer-destination" name="to_warehouse_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm" required></select>
                <p class="mt-1 text-sm text-red-700" data-field-error="to_warehouse_id"></p>
            </div>
        </div>
        <fieldset class="space-y-4">
            <legend class="font-semibold">Productos</legend>
            <p class="text-sm text-gintly-text-secondary">Las cantidades se expresan con máximo tres decimales.</p>
            <div class="space-y-4" data-operation-lines></div>
            <p class="text-sm text-red-700" data-field-error="items"></p>
            <button type="button" class="min-h-11 rounded-xl border border-gintly-brand px-4 font-semibold text-gintly-brand" data-add-line>Agregar producto</button>
        </fieldset>
        <div>
            <label for="transfer-notes" class="block text-sm font-semibold">Observaciones</label>
            <textarea id="transfer-notes" name="notes" rows="3" maxlength="500" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea>
            <p class="mt-1 text-sm text-red-700" data-field-error="notes"></p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-sm" data-operation-notice role="status" tabindex="-1" hidden></div>
        <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:opacity-60" data-operation-submit>Crear traspaso</button>
    </form>
</section>
@endsection
