@extends('layouts.panel')

@section('document-title', 'Apertura de caja')
@section('page-title', 'Apertura de caja')
@section('breadcrumb-root', 'Mi caja')
@section('breadcrumb-current', 'Apertura')
@section('page-script', 'operations/cash-open')

@section('content')
<section data-cash-open class="space-y-6" aria-labelledby="cash-open-title" aria-busy="true">
    <header class="rounded-3xl bg-gintly-sidebar p-6 text-white sm:p-8">
        <p class="text-sm font-semibold text-gintly-active">Inicio de jornada</p>
        <h1 id="cash-open-title" class="mt-2 text-3xl font-bold">Apertura de caja</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200">Cuenta el fondo inicial en cada moneda. El servidor registra los totales NIO y USD; este desglose es una ayuda de cálculo local, no evidencia histórica.</p>
    </header>
    <p data-cash-notice tabindex="-1" role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm" hidden></p>
    <button type="button" data-cash-verify hidden class="min-h-11 rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold">Verificar sesión antes de continuar</button>
    <p data-cash-loading role="status" class="rounded-2xl border border-slate-200 bg-white p-6">Verificando sesión y cajas disponibles…</p>
    <div data-cash-empty hidden class="rounded-2xl border border-amber-200 bg-amber-50 p-6"><p data-cash-empty-text class="text-sm text-amber-950"></p><a href="{{ route('panel.operations.cash') }}" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-gintly-sidebar px-5 text-sm font-semibold text-white">Volver a Mi caja</a></div>
    <form data-cash-form hidden class="space-y-6" novalidate>
        <div class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-2">
            <label class="grid min-w-0 gap-2 text-sm font-semibold">Caja asignada
                <input name="cash_register_id" readonly autocomplete="off" class="min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-slate-50 px-3" aria-describedby="cash-register-help cash-register-error">
            </label>
            <div class="self-center text-sm text-gintly-text-secondary"><p id="cash-register-help">Solo se muestra una caja activa asignada a ti en tu sucursal.</p><p data-cash-branch class="mt-2 font-semibold text-gintly-sidebar"></p></div>
            <p id="cash-register-error" data-error-for="cash_register_id" hidden class="text-sm text-red-700 md:col-span-2"></p>
        </div>
        @include('operations.partials.cash-grid')
        <p class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950">La tasa USD vigente y el consolidado NIO los determina el servidor. No se envía una tasa desde esta pantalla.</p>
        <div class="flex flex-wrap items-center gap-3"><button type="submit" data-cash-submit class="min-h-11 rounded-xl bg-gintly-brand px-6 text-sm font-semibold text-white disabled:opacity-50">Confirmar apertura</button><a href="{{ route('panel.operations.cash') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 text-sm font-semibold">Cancelar</a></div>
    </form>
</section>
@endsection
