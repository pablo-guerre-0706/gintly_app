<div data-operator-dashboard hidden>
    <div class="space-y-8" data-operator-content hidden>
        <section class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between" aria-labelledby="operator-dashboard-title">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gintly-brand">Mi jornada operativa</p>
                <h1 id="operator-dashboard-title" class="mt-1 text-[30px]/10 font-semibold tracking-[-0.5px] text-gintly-text-primary sm:text-[32px]/10">
                    Hola, <span data-operator-name></span>
                </h1>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gintly-text-secondary">
                    <span data-operator-branch></span>
                    <span aria-hidden="true">·</span>
                    <time data-operator-date></time>
                </div>
                <ul class="mt-4 flex flex-wrap gap-2" data-operator-profiles aria-label="Perfiles operativos asignados"></ul>
            </div>

            <button
                type="button"
                class="inline-flex min-h-[54px] items-center justify-center gap-2 self-start rounded-2xl bg-gintly-brand px-6 text-sm font-semibold text-white transition hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand disabled:cursor-wait disabled:opacity-60 lg:self-center"
                data-operator-refresh
            >
                <i class="fa-solid fa-rotate" data-operator-refresh-icon aria-hidden="true"></i>
                <span>Actualizar</span>
            </button>
        </section>

        <x-dashboard.async-section
            id="operator-status"
            title="Estado de la jornada"
            description="Situaciones reales de tu sucursal y de los perfiles que tienes asignados."
        >
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" data-operator-status-grid></div>
        </x-dashboard.async-section>

        <x-dashboard.async-section
            id="operator-actions"
            title="Acciones rápidas"
            description="Destinos disponibles según tus perfiles y capacidades efectivas."
        >
            <nav class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" data-operator-actions aria-label="Acciones operativas"></nav>
        </x-dashboard.async-section>

        <p class="sr-only" data-operator-live aria-live="polite"></p>
    </div>
</div>
