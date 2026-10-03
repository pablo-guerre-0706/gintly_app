@extends('layouts.panel')

@section('document-title', 'Despachos')
@section('page-title', 'Despachos')
@section('breadcrumb-root', 'Despachos y entregas')
@section('breadcrumb-current', 'Despachos')
@section('page-script', 'operations/dispatches')

@section('content')
<section class="space-y-6" data-operative-dispatches aria-labelledby="dispatches-title" aria-busy="true">
    <header>
        <p class="text-sm font-semibold text-gintly-brand">Sucursal asignada</p>
        <h1 id="dispatches-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Despachos y entregas</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary">Consulta los retiros de tu sucursal y registra entregas contra facturas autorizadas.</p>
    </header>

    <form class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-dispatch-form novalidate hidden aria-labelledby="new-dispatch-title">
        <div><h2 id="new-dispatch-title" class="text-lg font-semibold">Registrar despacho</h2><p class="mt-1 text-sm text-gintly-text-secondary">Primero consulta el saldo pendiente de la factura.</p></div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1"><label class="text-sm font-semibold" for="dispatch-invoice-id">ID de factura</label><input id="dispatch-invoice-id" name="invoice_id" type="number" min="1" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" required><p class="mt-1 text-sm text-red-700" data-dispatch-error="invoice_id"></p></div>
            <button type="button" class="min-h-11 rounded-xl border border-gintly-brand px-5 font-semibold text-gintly-brand disabled:opacity-60" data-dispatch-load-invoice>Consultar factura</button>
        </div>
        <div class="rounded-xl border border-slate-200" data-dispatch-lines-region hidden>
            <div class="border-b border-slate-200 p-4 text-sm font-semibold" data-delivery-status></div>
            <div class="divide-y divide-slate-200" data-dispatch-lines></div>
            <p class="p-4 text-sm text-red-700" data-dispatch-error="lines"></p>
        </div>
        <div><label class="text-sm font-semibold" for="dispatch-received-by">Recibido por</label><input id="dispatch-received-by" name="received_by" maxlength="160" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" required><p class="mt-1 text-sm text-red-700" data-dispatch-error="received_by"></p></div>
        <div><label class="text-sm font-semibold" for="dispatch-notes">Observaciones</label><textarea id="dispatch-notes" name="notes" rows="3" maxlength="500" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea><p class="mt-1 text-sm text-red-700" data-dispatch-error="notes"></p></div>
        <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:opacity-60" data-dispatch-submit disabled>Registrar despacho</button>
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="dispatch-history-title">
        <header class="flex items-center justify-between gap-4 border-b border-slate-200 p-5 sm:p-6"><div><h2 id="dispatch-history-title" class="font-semibold">Despachos de mi sucursal</h2><p class="mt-1 text-sm text-gintly-text-secondary" data-dispatch-summary></p></div><button type="button" class="grid size-11 place-items-center rounded-xl border border-slate-300" data-dispatch-refresh aria-label="Actualizar despachos"><i class="fa-solid fa-rotate" aria-hidden="true"></i></button></header>
        <div class="p-5 text-sm text-gintly-text-secondary" data-dispatch-state>Cargando despachos…</div>
        <ul class="divide-y divide-slate-200" data-dispatch-list hidden></ul>
    </section>
    <p class="sr-only" data-dispatch-live aria-live="polite"></p>
</section>
@endsection
