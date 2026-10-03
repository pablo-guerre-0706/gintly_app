@extends('layouts.panel')

@section('document-title', 'Nueva orden de compra')
@section('page-title', 'Nueva orden de compra')
@section('breadcrumb-root', 'Compras y recepción')
@section('breadcrumb-current', 'Nueva orden')
@section('page-script', 'operations/purchase-order-create')

@section('content')
<section class="mx-auto max-w-4xl space-y-6" data-purchase-order-create aria-labelledby="order-title" aria-busy="true">
    <header>
        <p class="text-sm font-semibold text-gintly-brand" data-operation-branch>Sucursal asignada</p>
        <h1 id="order-title" class="mt-2 text-2xl font-bold sm:text-3xl">Nueva orden de compra</h1>
        <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">El bodeguero registra un borrador para su sucursal. La emisión y aprobación no forman parte de esta operación.</p>
    </header>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-operation-loading role="status">Cargando proveedores y productos autorizados…</div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800" data-operation-fatal role="alert" hidden>
        <p data-operation-fatal-message></p>
        <button type="button" class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold" data-operation-retry>Reintentar</button>
    </div>

    <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-operation-form novalidate hidden>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="order-supplier" class="block text-sm font-semibold">Proveedor aprobado</label>
                <select id="order-supplier" name="supplier_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm" required></select>
                <p class="mt-1 text-sm text-red-700" data-field-error="supplier_id"></p>
            </div>
            <div>
                <label for="order-date" class="block text-sm font-semibold">Fecha de la orden</label>
                <input id="order-date" name="ordered_at" type="date" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" required>
                <p class="mt-1 text-sm text-red-700" data-field-error="ordered_at"></p>
            </div>
        </div>
        <fieldset class="space-y-4">
            <legend class="font-semibold">Líneas del borrador</legend>
            <p class="text-sm text-gintly-text-secondary">Cantidades: máximo tres decimales. Costo unitario pactado: máximo cuatro decimales.</p>
            <div class="space-y-4" data-operation-lines></div>
            <p class="text-sm text-red-700" data-field-error="items"></p>
            <button type="button" class="min-h-11 rounded-xl border border-gintly-brand px-4 font-semibold text-gintly-brand" data-add-line>Agregar producto</button>
        </fieldset>
        <div>
            <label for="order-notes" class="block text-sm font-semibold">Observaciones</label>
            <textarea id="order-notes" name="notes" rows="3" maxlength="500" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea>
            <p class="mt-1 text-sm text-red-700" data-field-error="notes"></p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-sm" data-operation-notice role="status" tabindex="-1" hidden></div>
        <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:opacity-60" data-operation-submit>Crear borrador</button>
    </form>
</section>
@endsection
