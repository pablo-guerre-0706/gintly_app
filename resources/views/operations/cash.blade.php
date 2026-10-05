@extends('layouts.panel')

@section('document-title', 'Mi caja')
@section('page-title', 'Mi caja')
@section('breadcrumb-root', 'Caja y cobros')
@section('breadcrumb-current', 'Mi caja')
@section('page-script', 'operations/cash')

@section('content')
<section data-cash-summary class="space-y-6" aria-labelledby="cash-summary-title" aria-busy="true">
    <header class="rounded-3xl bg-gintly-sidebar p-6 text-white sm:p-8">
        <p class="text-sm font-semibold text-gintly-active">Operación personal</p>
        <h1 id="cash-summary-title" class="mt-2 text-3xl font-bold">Mi caja</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200">Un lugar para consultar tu sesión y continuar cada operación en su propia pantalla.</p>
    </header>
    <p data-cash-notice tabindex="-1" role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm" hidden></p>
    <div data-cash-loading role="status" class="rounded-2xl border border-slate-200 bg-white p-6">Consultando tu sesión activa…</div>
    <div data-cash-ready hidden class="space-y-6">
        <p data-cash-assignment-empty hidden role="status" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">No tienes una caja asignada. El propietario o administrador debe asignarte una caja de tu sucursal antes de abrirla.</p>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de sesión">
            <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-gintly-text-secondary">Estado</p><p data-cash-status class="mt-2 text-lg font-bold"></p></article>
            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-gintly-text-secondary">Caja y sucursal</p><p data-cash-register class="mt-2 break-words text-lg font-bold"></p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-gintly-text-secondary">Fondo NIO / USD</p><p data-cash-opening class="mt-2 text-lg font-bold"></p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-gintly-text-secondary">Apertura</p><p data-cash-opened class="mt-2 text-lg font-bold"></p></article>
        </section>
        <p data-cash-blind-note class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950" hidden>Arqueo ciego activo: el esperado y la diferencia no se revelan antes de registrar un conteo o cerrar.</p>
        <nav aria-label="Operaciones de caja" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a data-cash-open-link href="{{ route('panel.operations.cash.open') }}" class="min-h-24 rounded-2xl border border-gintly-border bg-white p-5 font-semibold shadow-sm hover:border-gintly-brand">Abrir caja <span class="mt-2 block text-sm font-normal text-gintly-text-secondary">Disponible sin sesión activa</span></a>
            <a data-cash-movements-link href="{{ route('panel.operations.cash.movements') }}" class="min-h-24 rounded-2xl border border-gintly-border bg-white p-5 font-semibold shadow-sm hover:border-gintly-brand">Movimientos <span class="mt-2 block text-sm font-normal text-gintly-text-secondary">Registra y consulta tu sesión</span></a>
            <a data-cash-count-link href="{{ route('panel.operations.cash.count') }}" class="min-h-24 rounded-2xl border border-gintly-border bg-white p-5 font-semibold shadow-sm hover:border-gintly-brand">Arqueo independiente <span class="mt-2 block text-sm font-normal text-gintly-text-secondary">Cuenta sin cerrar</span></a>
            <a data-cash-close-link href="{{ route('panel.operations.cash.close') }}" class="min-h-24 rounded-2xl border border-gintly-border bg-white p-5 font-semibold shadow-sm hover:border-gintly-brand">Cerrar caja <span class="mt-2 block text-sm font-normal text-gintly-text-secondary">Conteo ciego y definitivo</span></a>
        </nav>
        <a href="{{ route('panel.operations.cash.history') }}" class="inline-flex min-h-11 items-center rounded-xl border border-gintly-border bg-white px-5 text-sm font-semibold text-gintly-sidebar">Consultar mis aperturas y cierres</a>
    </div>
    <button data-cash-retry type="button" hidden class="min-h-11 rounded-xl border border-gintly-border bg-white px-5 font-semibold">Reintentar consulta</button>
</section>
@endsection
