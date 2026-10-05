@extends('layouts.panel')

@section('document-title', 'Cajas registradoras')
@section('page-title', 'Cajas registradoras')
@section('breadcrumb-root', 'Caja y finanzas')
@section('breadcrumb-current', 'Cajas registradoras')
@section('page-script', 'administration/cash-registers')

@section('content')
<section class="space-y-6" data-admin-cash-registers aria-labelledby="cash-registers-title" aria-busy="true">
    <header class="flex flex-col gap-4 rounded-3xl border border-gintly-border bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-gintly-brand">Caja y finanzas</p>
            <h1 id="cash-registers-title" class="mt-2 text-2xl font-bold sm:text-3xl">Cajas registradoras</h1>
            <p class="mt-2 max-w-2xl text-sm text-gintly-text-secondary">Administra cajas por sucursal. Las sesiones y los arqueos pertenecen a cada caja, pero conservan su propio historial.</p>
        </div>
        <button type="button" data-register-create hidden class="min-h-11 shrink-0 rounded-xl bg-gintly-brand px-5 text-sm font-bold text-white">Crear caja</button>
    </header>

    <p data-register-notice role="status" tabindex="-1" hidden class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900"></p>

    <section class="overflow-hidden rounded-2xl border border-gintly-border bg-white shadow-sm" aria-labelledby="register-directory-title">
        <div class="flex flex-col gap-4 border-b border-gintly-border p-5 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 id="register-directory-title" class="text-lg font-bold">Directorio de cajas</h2><p data-register-total class="mt-1 text-sm text-gintly-text-secondary" aria-live="polite"></p></div>
            <form data-register-filters class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]" novalidate>
                <div><label for="register-filter-branch" class="block text-sm font-semibold">Sucursal</label><select id="register-filter-branch" name="branch_id" class="mt-2 min-h-11 w-full rounded-xl border border-gintly-border bg-white px-3 text-sm"><option value="">Todas</option></select></div>
                <div><label for="register-filter-status" class="block text-sm font-semibold">Estado</label><select id="register-filter-status" name="is_active" class="mt-2 min-h-11 w-full rounded-xl border border-gintly-border bg-white px-3 text-sm"><option value="">Todos</option><option value="1">Activas</option><option value="0">Inactivas</option></select></div>
                <button type="submit" class="min-h-11 rounded-xl border border-gintly-brand px-4 text-sm font-bold text-gintly-brand">Filtrar</button>
            </form>
        </div>
        <div data-register-rows class="grid min-w-0 gap-4 p-5 xl:grid-cols-2" aria-label="Cajas y sus cajeros asignados"></div>
        <div data-register-state role="status" class="p-5 text-sm text-gintly-text-secondary">Cargando cajas…</div>
        <nav data-register-pages hidden aria-label="Paginación de cajas" class="flex items-center justify-between gap-4 border-t border-gintly-border p-4"><button type="button" data-register-prev class="min-h-11 rounded-xl border border-gintly-border px-4 text-sm disabled:opacity-50">Anterior</button><span data-register-page class="text-sm"></span><button type="button" data-register-next class="min-h-11 rounded-xl border border-gintly-border px-4 text-sm disabled:opacity-50">Siguiente</button></nav>
    </section>

    <section data-register-form-region hidden class="rounded-2xl border border-gintly-border bg-white p-5 shadow-sm sm:p-7" aria-labelledby="register-form-title">
        <h2 id="register-form-title" data-register-form-title class="text-xl font-bold">Crear caja</h2>
        <p class="mt-2 text-sm text-gintly-text-secondary">La sucursal se establece al crear la caja y no puede cambiarse al editarla.</p>
        <form data-register-form novalidate class="mt-6 grid gap-5 sm:grid-cols-2">
            <p data-form-error-summary role="alert" tabindex="-1" hidden class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 sm:col-span-2"></p>
            <div><label for="register-name" class="block text-sm font-semibold">Nombre · Obligatorio</label><input id="register-name" name="name" required maxlength="100" autocomplete="off" aria-describedby="register-name-error" class="mt-2 min-h-11 w-full rounded-xl border border-gintly-border px-4 text-sm"><p id="register-name-error" data-error-for="name" class="mt-1 text-sm text-red-700"></p></div>
            <div><label for="register-branch" class="block text-sm font-semibold">Sucursal · Obligatorio al crear</label><select id="register-branch" name="branch_id" required aria-describedby="register-branch-error" class="mt-2 min-h-11 w-full rounded-xl border border-gintly-border bg-white px-4 text-sm"><option value="">Selecciona una sucursal</option></select><p id="register-branch-error" data-error-for="branch_id" class="mt-1 text-sm text-red-700"></p></div>
            <label class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input name="is_active" type="checkbox" class="size-5" checked>Activa</label>
            <div data-register-cashier-block class="min-w-0 sm:col-span-2"><label for="register-cashier" class="block text-sm font-semibold">Cajero inicial · Opcional</label><select id="register-cashier" name="assignment_user_id" disabled aria-describedby="register-cashier-help" class="mt-2 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 px-3"><option value="">Asignar después</option></select><p id="register-cashier-help" data-register-cashier-help class="mt-2 text-sm leading-6 text-gintly-text-secondary"></p></div>
            <div class="flex flex-wrap gap-3 sm:col-span-2"><button type="submit" data-register-save class="min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-bold text-white disabled:opacity-60">Guardar caja</button><button type="button" data-register-cancel class="min-h-11 rounded-xl border border-gintly-border px-5 text-sm font-semibold">Cancelar</button></div>
        </form>
    </section>

    <dialog data-register-confirm class="m-auto w-[min(420px,calc(100vw-2rem))] rounded-2xl border border-gintly-border bg-white p-6 text-gintly-text-primary shadow-2xl backdrop:bg-slate-950/60" aria-labelledby="register-confirm-title" aria-describedby="register-confirm-message">
        <h2 id="register-confirm-title" class="text-lg font-bold">Confirmar acción</h2>
        <p id="register-confirm-message" data-register-confirm-message class="mt-3 text-sm leading-6 text-gintly-text-secondary"></p>
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <button type="button" data-register-confirm-cancel class="min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand">Volver</button>
            <button type="button" data-register-confirm-accept class="min-h-11 rounded-xl bg-gintly-brand px-4 text-sm font-bold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand">Confirmar</button>
        </div>
    </dialog>
    @include('administration.partials.cash-assignment-dialog')
</section>
@endsection
