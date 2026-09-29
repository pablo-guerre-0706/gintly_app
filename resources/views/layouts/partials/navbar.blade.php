<header class="sticky top-0 z-40 flex h-16 min-w-0 items-center justify-between gap-3 border-b border-gintly-border bg-white/95 px-4 backdrop-blur-md md:h-[72px] md:px-6 lg:h-[88px] xl:h-[108px] xl:gap-8 xl:px-8 xl:py-6" data-shell-header>
    <div class="flex min-w-0 flex-1 items-center gap-3 lg:gap-5" data-search-background>
        <button
            type="button"
            class="grid size-11 shrink-0 place-items-center rounded-xl text-gintly-sidebar transition hover:bg-gintly-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand lg:hidden"
            data-drawer-trigger
            aria-controls="panel-sidebar"
            aria-expanded="false"
            aria-label="Abrir navegación"
        >
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>

        <div class="min-w-0 flex-1 lg:flex-none lg:max-w-[320px] xl:max-w-[420px]">
            <nav class="hidden min-w-0 items-center gap-2 text-[18px]/[26px] sm:flex" aria-label="Miga de pan">
                <span class="truncate font-normal text-gintly-text-secondary" title="{{ $breadcrumbRoot }}">
                    {{ $breadcrumbRoot }}
                </span>
                <i class="fa-solid fa-chevron-right shrink-0 text-xs text-gintly-border" aria-hidden="true"></i>
                <span
                    class="truncate font-semibold text-gintly-text-primary"
                    aria-current="page"
                    title="{{ $breadcrumbCurrent }}"
                >
                    {{ $breadcrumbCurrent }}
                </span>
            </nav>
            <p class="truncate text-base font-semibold text-gintly-text-primary sm:hidden" title="{{ $pageTitle }}">
                {{ $pageTitle }}
            </p>
        </div>
    </div>

    <div class="flex shrink-0 items-center justify-end gap-2 sm:gap-3 lg:flex-1 lg:gap-4">
        <button
            type="button"
            class="grid size-11 place-items-center rounded-xl text-gintly-text-secondary transition hover:bg-gintly-control hover:text-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand md:hidden"
            data-mobile-search-trigger
            data-search-background
            aria-controls="navigation-search"
            aria-expanded="false"
            aria-label="Buscar en la navegación"
        >
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </button>

        <div
            id="navigation-search"
            class="shell-navigation-search fixed inset-0 z-[80] hidden bg-slate-950/60 p-3 backdrop-blur-[2px] md:relative md:inset-auto md:z-auto md:block md:w-[clamp(260px,36vw,480px)] md:bg-transparent md:p-0 md:backdrop-blur-none xl:w-[min(40vw,606px)]"
            data-navigation-search
        >
            <div class="relative mx-auto w-full max-w-[606px] rounded-2xl bg-white p-4 shadow-2xl md:max-w-none md:rounded-none md:bg-transparent md:p-0 md:shadow-none" role="search">
                <div class="flex items-center justify-between gap-3 md:hidden">
                    <h2 id="navigation-search-title" class="text-lg font-semibold text-gintly-text-primary">Buscar en la navegación</h2>
                    <button
                        type="button"
                        class="grid size-11 place-items-center rounded-xl text-gintly-text-secondary hover:bg-gintly-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand"
                        data-mobile-search-close
                        aria-label="Cerrar búsqueda"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 text-gintly-text-secondary" aria-hidden="true"></i>
                    <input
                        type="search"
                        class="h-[42px] w-full rounded-2xl border border-gintly-border bg-gintly-control py-2 pe-11 ps-11 text-sm text-gintly-text-primary outline-none transition placeholder:text-gintly-text-secondary focus:border-gintly-brand focus:bg-white focus:ring-2 focus:ring-gintly-brand/20"
                        placeholder="Buscar en la navegación"
                        autocomplete="off"
                        data-navigation-search-input
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls="navigation-search-results"
                        aria-expanded="false"
                        aria-label="Buscar destinos de navegación"
                    >
                    <kbd class="pointer-events-none absolute end-3 top-1/2 hidden -translate-y-1/2 rounded-md border border-gintly-border bg-white px-1.5 py-0.5 text-[11px] text-gintly-text-secondary xl:block">⌘K</kbd>
                </div>

                <div class="shell-search-popover" id="navigation-search-results" data-navigation-search-results role="listbox" hidden></div>
                <p class="shell-search-popover text-center text-sm text-gintly-text-secondary" data-navigation-search-empty hidden>Sin coincidencias</p>
            </div>
        </div>

        <div class="relative" data-search-background>
            <button
                type="button"
                class="group flex min-h-11 items-center gap-3 rounded-2xl px-1.5 py-1 text-start transition hover:bg-gintly-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand disabled:cursor-wait disabled:opacity-60 sm:px-2"
                data-account-trigger
                aria-controls="account-menu"
                aria-expanded="false"
                aria-haspopup="dialog"
                aria-busy="true"
                aria-label="Abrir menú de cuenta"
                disabled
            >
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-gintly-sidebar text-sm font-semibold text-white xl:size-[60px] xl:text-base" data-user-initials aria-hidden="true">…</span>
                <span class="hidden min-w-0 max-w-40 xl:block">
                    <span class="block truncate text-sm font-semibold text-gintly-text-primary" data-user-name>Cargando…</span>
                    <span class="block truncate text-xs text-gintly-text-secondary" data-user-role></span>
                </span>
                <i class="fa-solid fa-chevron-down hidden text-xs text-gintly-text-secondary transition group-aria-expanded:rotate-180 sm:block" aria-hidden="true"></i>
            </button>

            <div id="account-menu" class="absolute end-0 top-[calc(100%+0.75rem)] z-[70] w-[min(320px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" data-account-menu role="dialog" aria-label="Cuenta" hidden>
                <div class="border-b border-slate-200 px-4 py-4">
                    <p class="truncate font-semibold text-gintly-text-primary" data-account-name></p>
                    <p class="mt-0.5 truncate text-sm text-gintly-text-secondary" data-account-email hidden></p>
                </div>
                <dl class="space-y-3 px-4 py-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gintly-text-secondary">Rol</dt>
                        <dd class="mt-0.5 font-medium text-gintly-text-primary" data-account-role></dd>
                    </div>
                    <div data-account-profiles-row hidden>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gintly-text-secondary">Perfiles operativos</dt>
                        <dd class="mt-0.5 text-gintly-text-primary" data-account-profiles></dd>
                    </div>
                    <div data-account-business-row hidden>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gintly-text-secondary">Negocio</dt>
                        <dd class="mt-0.5 text-gintly-text-primary" data-account-business></dd>
                    </div>
                    <div data-account-branch-row hidden>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gintly-text-secondary">Sucursal</dt>
                        <dd class="mt-0.5 text-gintly-text-primary" data-account-branch></dd>
                    </div>
                    <div data-account-status-row hidden>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gintly-text-secondary">Estado</dt>
                        <dd class="mt-0.5 text-gintly-text-primary" data-account-status></dd>
                    </div>
                </dl>
                <div class="border-t border-slate-200 p-2">
                    <button type="button" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300" data-logout>
                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                        <span data-logout-label>Cerrar sesión</span>
                        <i class="fa-solid fa-circle-notch fa-spin ms-auto" data-logout-spinner aria-hidden="true" hidden></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
