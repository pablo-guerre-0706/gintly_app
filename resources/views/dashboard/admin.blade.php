<div class="space-y-8" data-admin-dashboard hidden>
    <section class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between" aria-labelledby="admin-dashboard-title">
        <div class="min-w-0">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Administración general</p>
            <h1 id="admin-dashboard-title" class="mt-2 text-[32px]/10 font-semibold tracking-[-0.5px] text-gintly-text-primary">Panel administrativo</h1>
            <p class="mt-3 text-sm/5 text-gintly-text-secondary" data-admin-dashboard-context>Supervisión operativa del negocio</p>
        </div>
        <button type="button" class="inline-flex min-h-[54px] items-center justify-center gap-2 self-start rounded-2xl bg-gintly-brand px-6 text-sm font-semibold text-white transition hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand disabled:cursor-wait disabled:opacity-60 lg:self-center" data-admin-dashboard-refresh>
            <i class="fa-solid fa-rotate" data-refresh-icon aria-hidden="true"></i>
            <span data-refresh-label>Actualizar</span>
        </button>
    </section>

    <x-dashboard.async-section id="admin-summary" title="Resumen administrativo" description="Contadores contractuales del negocio que requieren supervisión o seguimiento.">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-admin-summary-grid></div>
    </x-dashboard.async-section>

    <aside class="overflow-hidden rounded-3xl border border-gintly-border bg-white shadow-sm" aria-labelledby="admin-suppliers-title">
        <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-start">
                <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-gintly-brand/10 text-2xl text-gintly-brand" aria-hidden="true"><i class="fa-solid fa-map-location-dot"></i></span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 id="admin-suppliers-title" class="text-xl font-bold text-gintly-text-primary">Explorar proveedores cercanos</h2>
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-900">Externo</span>
                    </div>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gintly-text-secondary">Consulta el estado de disponibilidad de la futura exploración externa, separada de los proveedores registrados.</p>
                </div>
            </div>
            <a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand focus-visible:ring-offset-2" href="{{ route('panel.suppliers.explore') }}">Ver disponibilidad <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </aside>

    <div class="grid gap-8 xl:grid-cols-2">
        <x-dashboard.async-section id="admin-anomalies" title="Anomalías que requieren intervención" description="Hasta cinco anomalías activas priorizadas por severidad y fecha.">
            <ul class="space-y-3" data-admin-anomaly-list></ul>
            <a class="mt-5 inline-flex min-h-11 items-center gap-2 rounded-xl border border-gintly-border px-4 text-sm font-semibold text-gintly-brand hover:bg-gintly-control" href="{{ route('panel.anomalies.index') }}">Abrir centro de anomalías <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </x-dashboard.async-section>

        <x-dashboard.async-section id="admin-cash" title="Sesiones de caja abiertas" description="Seguimiento administrativo sin revelar importes esperados del arqueo.">
            <ul class="divide-y divide-slate-200" data-admin-cash-list></ul>
            <a class="mt-5 inline-flex min-h-11 items-center gap-2 rounded-xl border border-gintly-border px-4 text-sm font-semibold text-gintly-brand hover:bg-gintly-control" href="{{ route('panel.admin.cash-sessions') }}">Supervisar sesiones <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </x-dashboard.async-section>
    </div>

    <section class="rounded-3xl border border-gintly-border bg-white p-6 shadow-sm sm:p-8" aria-labelledby="admin-shortcuts-title">
        <div class="max-w-3xl">
            <h2 id="admin-shortcuts-title" class="text-xl font-bold text-gintly-text-primary">Accesos administrativos</h2>
            <p class="mt-2 text-sm leading-6 text-gintly-text-secondary">Solo se muestran destinos con ruta web real y capacidad efectiva en el contexto autenticado.</p>
        </div>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-admin-shortcuts>
            @php
                $adminShortcuts = [
                    ['usuarios.ver', 'fa-users-gear', 'Usuarios', route('panel.users.index')],
                    ['usuarios.gestionar', 'fa-user-plus', 'Crear usuario operativo', route('panel.users.create')],
                    ['usuarios.gestionar', 'fa-id-badge', 'Perfiles operativos', route('panel.profiles.index')],
                    ['sucursales.gestionar', 'fa-code-branch', 'Sucursales', route('panel.branches.index')],
                    ['catalogo.ver', 'fa-box-open', 'Productos y catálogo', route('catalog.products')],
                    ['bodegas.ver', 'fa-warehouse', 'Bodegas', route('panel.admin.warehouses')],
                    ['inventario.ver', 'fa-layer-group', 'Inventario lógico', route('panel.inventory.stock')],
                    ['caja.gestionar', 'fa-cash-register', 'Cajas registradoras', route('panel.admin.cash-registers')],
                    ['proveedores.ver', 'fa-building', 'Proveedores', route('panel.suppliers.index')],
                    ['compras.ver', 'fa-file-circle-check', 'Órdenes de compra', route('panel.admin.purchase-orders')],
                    ['anomalias.ver', 'fa-triangle-exclamation', 'Anomalías', route('panel.anomalies.index')],
                    ['conciliacion.ver', 'fa-scale-balanced', 'Conciliaciones', route('panel.reconciliations.index')],
                    ['auditoria.ver', 'fa-shield-halved', 'Auditoría', route('panel.audit.index')],
                ];
            @endphp
            @foreach ($adminShortcuts as [$capability, $icon, $label, $url])
                <a class="flex min-h-16 items-center gap-3 rounded-2xl border border-gintly-border px-4 py-3 text-sm font-semibold text-gintly-text-primary transition hover:border-gintly-brand hover:bg-gintly-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" href="{{ $url }}" data-admin-shortcut data-capability="{{ $capability }}" hidden>
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-gintly-brand/10 text-gintly-brand" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
        <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-gintly-text-secondary" data-admin-shortcuts-empty hidden>No hay accesos administrativos habilitados para las capacidades actuales.</p>
    </section>
</div>
