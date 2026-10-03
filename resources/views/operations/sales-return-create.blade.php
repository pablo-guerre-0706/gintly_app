@extends('layouts.panel')

@section('document-title', 'Registrar devolución')
@section('page-title', 'Registrar devolución')
@section('breadcrumb-root', 'Devoluciones')
@section('breadcrumb-current', 'Registrar devolución')
@section('page-script', 'operations/sales-return-create')

@section('content')
<section class="mx-auto max-w-4xl space-y-6" data-sales-return-create aria-labelledby="return-title" aria-busy="true">
    <header>
        <p class="text-sm font-semibold text-gintly-brand" data-operation-branch>Sucursal asignada</p>
        <h1 id="return-title" class="mt-2 text-2xl font-bold text-gintly-text-primary sm:text-3xl">Registrar devolución</h1>
        <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Selecciona una factura y una línea devolvible de tu sucursal. Solo se muestran los datos mínimos necesarios; el servidor vuelve a validar cantidades y autorizaciones al registrar.</p>
    </header>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-operation-loading role="status">Cargando facturas elegibles…</div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800" data-operation-fatal role="alert" hidden>
        <p data-operation-fatal-message></p>
        <button type="button" class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold" data-operation-retry>Reintentar</button>
    </div>

    <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" data-operation-form novalidate hidden>
        <div>
            <label for="return-invoice" class="block text-sm font-semibold">Factura devolvible</label>
            <select id="return-invoice" name="invoice_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm" required></select>
            <p class="mt-1 text-sm text-red-700" data-field-error="invoice_id"></p>
            <button type="button" class="mt-2 min-h-11 rounded-xl border border-gintly-brand px-4 text-sm font-semibold text-gintly-brand" data-more-invoices hidden>Cargar más facturas</button>
        </div>

        <div data-return-items-loading role="status" hidden>Cargando líneas devolvibles…</div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" data-return-items-error role="alert" hidden>
            <p data-return-items-error-message></p>
            <button type="button" class="mt-2 min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold" data-return-items-retry>Reintentar líneas</button>
        </div>
        <fieldset class="space-y-4" data-return-lines-fieldset hidden>
            <legend class="font-semibold">Productos a devolver</legend>
            <p class="text-sm text-gintly-text-secondary">La cantidad máxima devolvible se muestra por línea. Puedes registrar una o varias líneas de esta factura.</p>
            <div class="space-y-4" data-operation-lines></div>
            <p class="text-sm text-red-700" data-field-error="lines"></p>
            <button type="button" class="min-h-11 rounded-xl border border-gintly-brand px-4 font-semibold text-gintly-brand" data-add-line>Agregar línea</button>
        </fieldset>
        <div>
            <label for="return-notes" class="block text-sm font-semibold">Observaciones</label>
            <textarea id="return-notes" name="notes" rows="3" maxlength="500" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea>
            <p class="mt-1 text-sm text-red-700" data-field-error="notes"></p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4 text-sm" data-operation-notice role="status" tabindex="-1" hidden></div>
        <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:opacity-60" data-operation-submit disabled>Registrar devolución</button>
    </form>
</section>
@endsection
