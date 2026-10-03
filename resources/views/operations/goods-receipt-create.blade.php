@extends('layouts.panel')

@section('document-title', 'Registrar recepción')
@section('page-title', 'Registrar recepción')
@section('breadcrumb-root', 'Compras y recepción')
@section('breadcrumb-current', 'Registrar recepción')
@section('page-script', 'operations/goods-receipt-create')

@section('content')
<section class="mx-auto max-w-4xl space-y-6" data-goods-receipt-create aria-labelledby="receipt-title" aria-busy="true">
    <header>
        <p class="text-sm font-semibold text-gintly-brand" data-operation-branch>Sucursal asignada</p>
        <h1 id="receipt-title" class="mt-2 text-2xl font-bold sm:text-3xl">Registrar recepción</h1>
        <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Recibe únicamente órdenes emitidas o parciales y bodegas de tu sucursal. Una discrepancia puede quedar registrada aunque el servidor responda 409.</p>
    </header>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-operation-loading role="status">Cargando órdenes y bodegas…</div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800" data-operation-fatal role="alert" hidden>
        <p data-operation-fatal-message></p>
        <button type="button" class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold" data-operation-retry>Reintentar</button>
    </div>

    <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-operation-form novalidate hidden>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="receipt-order" class="block text-sm font-semibold">Orden recepcionable</label>
                <select id="receipt-order" name="purchase_order_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm" required></select>
                <p class="mt-1 text-sm text-red-700" data-field-error="purchase_order_id"></p>
            </div>
            <div>
                <label for="receipt-warehouse" class="block text-sm font-semibold">Bodega receptora</label>
                <select id="receipt-warehouse" name="warehouse_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm" required></select>
                <p class="mt-1 text-sm text-red-700" data-field-error="warehouse_id"></p>
            </div>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="receipt-invoice-number" class="block text-sm font-semibold">Número de factura del proveedor (opcional)</label>
                <input id="receipt-invoice-number" name="supplier_invoice_number" maxlength="60" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                <p class="mt-1 text-sm text-red-700" data-field-error="supplier_invoice_number"></p>
            </div>
            <div>
                <label for="receipt-invoice-total" class="block text-sm font-semibold">Total facturado (opcional)</label>
                <input id="receipt-invoice-total" name="supplier_invoice_total" inputmode="decimal" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="0.00">
                <p class="mt-1 text-sm text-red-700" data-field-error="supplier_invoice_total"></p>
            </div>
        </div>
        <div>
            <label for="receipt-tolerance" class="block text-sm font-semibold">Tolerancia de costo (opcional)</label>
            <input id="receipt-tolerance" name="tolerance" inputmode="decimal" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="0.0000">
            <p class="mt-1 text-sm text-red-700" data-field-error="tolerance"></p>
        </div>
        <fieldset class="space-y-4">
            <legend class="font-semibold">Líneas pendientes de la orden</legend>
            <div class="space-y-4" data-operation-lines aria-live="polite"></div>
            <p class="text-sm text-red-700" data-field-error="lines"></p>
        </fieldset>
        <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:opacity-60" data-operation-submit>Registrar recepción</button>
    </form>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm" data-operation-notice role="status" tabindex="-1" hidden></div>
</section>
@endsection
