@extends('layouts.panel')

@section('document-title', 'Movimientos de mi caja')
@section('page-title', 'Movimientos de mi caja')
@section('breadcrumb-root', 'Mi caja')
@section('breadcrumb-current', 'Movimientos')
@section('page-script', 'operations/cash-movements')

@section('content')
<section data-cash-movements class="space-y-6" aria-labelledby="cash-movements-title" aria-busy="true">
    <header class="rounded-3xl bg-gintly-sidebar p-6 text-white sm:p-8"><p class="text-sm font-semibold text-gintly-active">Sesión propia</p><h1 id="cash-movements-title" class="mt-2 text-3xl font-bold">Movimientos de mi caja</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200">Registra ingresos y egresos en su moneda nativa. El servidor congela la tasa USD por operación; transferencia y tarjeta no aumentan el efectivo de la gaveta.</p></header>
    <p data-cash-notice tabindex="-1" role="alert" hidden class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm"></p>
    <p data-cash-loading role="status" class="rounded-2xl border border-slate-200 bg-white p-6">Cargando sesión…</p>
    <p data-cash-empty hidden class="rounded-2xl border border-slate-200 bg-white p-6 text-sm">No tienes una sesión abierta. <a href="{{ route('panel.operations.cash') }}" class="font-semibold text-gintly-brand underline">Volver a Mi caja</a></p>
    <div data-cash-content hidden class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <form data-cash-form novalidate class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 class="text-lg font-bold">Registrar movimiento</h2><p data-cash-session class="text-sm text-gintly-text-secondary"></p>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-2 text-sm font-semibold">Tipo · Obligatorio<select name="type" required class="min-h-11 rounded-xl border border-slate-300 px-3"><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select></label>
                <label class="grid gap-2 text-sm font-semibold">Categoría · Obligatorio<select name="category" required class="min-h-11 rounded-xl border border-slate-300 px-3"><option value="ajuste">Ajuste</option><option value="retiro">Retiro</option></select></label>
                <label class="grid gap-2 text-sm font-semibold">Medio de pago · Obligatorio<select name="payment_method" required class="min-h-11 rounded-xl border border-slate-300 px-3"><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Transferencia</option></select></label>
                <label class="grid gap-2 text-sm font-semibold">Moneda · Obligatorio<select name="currency" required class="min-h-11 rounded-xl border border-slate-300 px-3"><option value="NIO">Córdobas · NIO</option><option value="USD">Dólares · USD</option></select></label>
                <label class="grid gap-2 text-sm font-semibold sm:col-span-2">Monto · Obligatorio<input name="amount" required inputmode="decimal" autocomplete="off" placeholder="0.00" class="min-h-11 rounded-xl border border-slate-300 px-3" aria-describedby="cash-movement-amount-error"><span id="cash-movement-amount-error" data-error-for="amount" hidden class="text-red-700"></span></label>
                <label class="grid gap-2 text-sm font-semibold sm:col-span-2">Descripción · Opcional<input name="description" maxlength="255" class="min-h-11 rounded-xl border border-slate-300 px-3"></label>
            </div>
            <p data-cash-field-error role="alert" hidden class="text-sm text-red-700"></p>
            <button data-cash-submit type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-6 text-sm font-semibold text-white disabled:opacity-50">Registrar movimiento</button>
        </form>
        <section aria-labelledby="cash-movement-history-title" class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><h2 id="cash-movement-history-title" class="text-lg font-bold">Historial append-only</h2><p data-cash-list-state role="status" class="mt-2 text-sm text-gintly-text-secondary">Cargando movimientos…</p><ol data-cash-list class="mt-4 divide-y divide-slate-200"></ol><div class="mt-4 flex items-center justify-between gap-2"><button data-cash-prev type="button" class="min-h-11 rounded-xl border px-4 disabled:opacity-40">Anterior</button><span data-cash-page class="text-sm"></span><button data-cash-next type="button" class="min-h-11 rounded-xl border px-4 disabled:opacity-40">Siguiente</button></div><button data-cash-retry type="button" hidden class="mt-4 min-h-11 rounded-xl border px-4">Reintentar</button></section>
    </div>
</section>
@endsection
