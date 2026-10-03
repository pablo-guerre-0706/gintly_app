@extends('layouts.panel')

@section('document-title', 'Cobros pendientes')
@section('page-title', 'Cobros pendientes')
@section('breadcrumb-root', 'Caja y cobros')
@section('breadcrumb-current', 'Cobros pendientes')
@section('page-script', 'operations/receivables')

@section('content')
<section class="space-y-6" data-operative-receivables aria-labelledby="receivables-title" aria-busy="true">
    <header>
        <p class="text-sm font-semibold text-gintly-brand">Sucursal asignada</p>
        <h1 id="receivables-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Cobros pendientes</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary">Localiza cuentas cobrables de tu sucursal y registra abonos autorizados.</p>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="receivable-list-title">
        <header class="border-b border-slate-200 p-5 sm:flex sm:items-end sm:justify-between sm:gap-5 sm:p-6">
            <div><h2 id="receivable-list-title" class="font-semibold">Cuentas disponibles</h2><p class="mt-1 text-sm text-gintly-text-secondary" data-receivables-summary aria-live="polite"></p></div>
            <label class="mt-4 block sm:mt-0 sm:w-80"><span class="sr-only">Buscar por cliente, documento o folio</span><input type="search" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm" placeholder="Cliente, documento o folio" data-receivables-search></label>
        </header>
        <div class="p-5 text-sm text-gintly-text-secondary" data-receivables-state>Cargando cuentas…</div>
        <ul class="divide-y divide-slate-200" data-receivables-list hidden></ul>
    </section>

    <form class="space-y-5 rounded-2xl border border-gintly-brand/30 bg-white p-5 sm:p-7" data-receivable-payment-form novalidate hidden aria-labelledby="receivable-payment-title">
        <div class="flex items-start justify-between gap-4">
            <div><h2 id="receivable-payment-title" class="text-lg font-semibold">Registrar abono</h2><p class="mt-1 text-sm text-gintly-text-secondary" data-receivable-selection></p></div>
            <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-300" data-receivable-cancel aria-label="Cancelar abono"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <input type="hidden" name="receivable_id">
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label class="text-sm font-semibold" for="receivable-payment-amount">Monto</label><input id="receivable-payment-amount" name="amount" type="text" inputmode="decimal" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" required><p class="mt-1 text-sm text-red-700" data-payment-error="amount"></p></div>
            <div><label class="text-sm font-semibold" for="receivable-payment-method">Medio de pago</label><select id="receivable-payment-method" name="payment_method" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="tarjeta">Tarjeta</option></select><p class="mt-1 text-sm text-red-700" data-payment-error="payment_method"></p></div>
        </div>
        <div><label class="text-sm font-semibold" for="receivable-payment-reference">Referencia</label><input id="receivable-payment-reference" name="reference" maxlength="100" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"><p class="mt-1 text-sm text-red-700" data-payment-error="reference"></p></div>
        <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900" data-cash-payment-warning hidden>Los pagos en efectivo requieren tu propia sesión de caja abierta.</p>
        <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:opacity-60" data-receivable-payment-submit>Registrar abono</button>
    </form>
    <p class="sr-only" data-receivables-live aria-live="polite"></p>
</section>
@endsection
