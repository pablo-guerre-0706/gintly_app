@extends('layouts.panel')

@section('document-title', 'Mi caja')
@section('page-title', 'Mi caja')
@section('breadcrumb-root', 'Caja y cobros')
@section('breadcrumb-current', 'Mi caja')
@section('page-script', 'operations/cash')

@section('content')
<section class="space-y-6" data-operator-cash aria-labelledby="operator-cash-title" aria-busy="true">
    <header>
        <p class="text-sm font-semibold text-gintly-brand">Operación personal</p>
        <h1 id="operator-cash-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Mi caja</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary">Abre y opera únicamente tu propia sesión en la sucursal asignada.</p>
    </header>

    <div class="rounded-2xl border border-slate-200 bg-white p-6" data-cash-page-state role="status">Consultando tu sesión activa…</div>

    <form class="max-w-2xl space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-cash-open-form novalidate hidden>
        <div>
            <h2 class="text-lg font-semibold text-gintly-text-primary">Abrir sesión de caja</h2>
            <p class="mt-1 text-sm text-gintly-text-secondary">Selecciona una caja activa de tu sucursal e indica el fondo inicial.</p>
        </div>
        <div>
            <label class="text-sm font-semibold" for="cash-register-id">Caja</label>
            <select id="cash-register-id" name="cash_register_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" required></select>
            <p class="mt-1 text-sm text-red-700" data-cash-error="cash_register_id"></p>
        </div>
        <div>
            <label class="text-sm font-semibold" for="cash-opening-amount">Fondo inicial</label>
            <input id="cash-opening-amount" name="opening_amount" type="text" inputmode="decimal" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="0.00" required>
            <p class="mt-1 text-sm text-red-700" data-cash-error="opening_amount"></p>
        </div>
        <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:opacity-60" data-cash-open-submit>Abrir caja</button>
    </form>

    <div class="space-y-6" data-cash-active hidden>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Sesión activa">
            <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs text-gintly-text-secondary">Sesión</p><p class="mt-2 font-semibold" data-cash-session-id></p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs text-gintly-text-secondary">Caja</p><p class="mt-2 font-semibold" data-cash-register-name></p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs text-gintly-text-secondary">Fondo inicial</p><p class="mt-2 font-semibold" data-cash-opening></p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs text-gintly-text-secondary">Apertura</p><p class="mt-2 font-semibold" data-cash-opened-at></p></article>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)]">
            <form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" data-cash-movement-form novalidate hidden>
                <div><h2 class="font-semibold">Registrar movimiento manual</h2><p class="mt-1 text-sm text-gintly-text-secondary">Los cobros y ventas se registran desde sus flujos correspondientes.</p></div>
                <div>
                    <label class="text-sm font-semibold" for="cash-movement-category">Categoría</label>
                    <select id="cash-movement-category" name="category" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                        <option value="retiro">Retiro</option>
                        <option value="ajuste">Ajuste</option>
                    </select>
                </div>
                <div data-cash-movement-type-row hidden>
                    <label class="text-sm font-semibold" for="cash-movement-type">Dirección del ajuste</label>
                    <select id="cash-movement-type" name="type" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select>
                </div>
                <div>
                    <label class="text-sm font-semibold" for="cash-movement-amount">Monto</label>
                    <input id="cash-movement-amount" name="amount" type="text" inputmode="decimal" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="0.00" required>
                    <p class="mt-1 text-sm text-red-700" data-cash-error="amount"></p>
                </div>
                <div><label class="text-sm font-semibold" for="cash-movement-description">Descripción</label><input id="cash-movement-description" name="description" maxlength="255" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"></div>
                <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:opacity-60" data-cash-movement-submit>Registrar movimiento</button>
            </form>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="cash-movements-title">
                <header class="flex items-center justify-between gap-4 border-b border-slate-200 p-5"><div><h2 id="cash-movements-title" class="font-semibold">Movimientos de mi sesión</h2><p class="mt-1 text-sm text-gintly-text-secondary" data-cash-movements-summary></p></div><button type="button" class="grid size-11 place-items-center rounded-xl border border-slate-300" data-cash-refresh aria-label="Actualizar movimientos"><i class="fa-solid fa-rotate" aria-hidden="true"></i></button></header>
                <div class="p-5 text-sm text-gintly-text-secondary" data-cash-movements-state>Cargando movimientos…</div>
                <ul class="divide-y divide-slate-200" data-cash-movements hidden></ul>
            </section>
        </div>

        <div class="flex justify-end">
            <a class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gintly-brand px-5 font-semibold text-gintly-brand hover:bg-gintly-control" href="{{ route('finance.cash-closing') }}" data-cash-close-link hidden>Ir al arqueo y cierre</a>
        </div>
    </div>
    <p class="sr-only" data-cash-live aria-live="polite"></p>
</section>
@endsection
