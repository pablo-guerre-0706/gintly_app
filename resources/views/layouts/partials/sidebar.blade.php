<aside
    id="panel-sidebar"
    class="shell-sidebar fixed inset-y-0 start-0 z-[60] flex h-dvh min-h-0 flex-col overflow-hidden bg-gintly-sidebar text-white shadow-[18px_0_48px_rgb(8_44_55/0.22)] transition-[width,transform] duration-200"
    data-shell-sidebar
    aria-label="Navegación principal"
    tabindex="-1"
>
    <div class="flex h-[88px] shrink-0 items-center gap-3 border-b border-white/10 px-4">
        <a
            href="{{ route('dashboard') }}"
            class="flex min-w-0 flex-1 items-center gap-3 rounded-xl px-2 py-2 text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-active"
            aria-label="Gintly, ir al panel"
            data-sidebar-link
            data-nav-tooltip="Panel"
        >
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-gintly-brand text-xl" aria-hidden="true">
                <i class="fa-solid fa-g" aria-hidden="true"></i>
            </span>
            <span class="shell-sidebar-label min-w-0">
                <span class="block truncate text-xl font-semibold tracking-tight">Gintly</span>
                <span class="block truncate text-xs text-white/65">Gestión empresarial</span>
            </span>
        </a>

        <button
            type="button"
            class="grid size-11 shrink-0 place-items-center rounded-xl text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-active"
            data-sidebar-toggle
            data-nav-tooltip="Contraer navegación"
            aria-controls="panel-sidebar"
            aria-expanded="true"
            aria-label="Contraer navegación"
        >
            <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
        </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto px-3 py-5">
        <div class="space-y-3 px-2" data-sidebar-loading aria-live="polite">
            <span class="sr-only">Cargando navegación</span>
            <div class="h-11 animate-pulse rounded-xl bg-white/10"></div>
            <div class="h-11 animate-pulse rounded-xl bg-white/10"></div>
            <div class="h-11 animate-pulse rounded-xl bg-white/10"></div>
        </div>

        <div class="rounded-2xl border border-white/15 bg-white/5 p-4 text-sm text-white" data-sidebar-error role="alert" hidden>
            <p class="font-semibold">No fue posible cargar tu acceso.</p>
            <p class="mt-1 text-white/70" data-sidebar-error-message>Comprueba tu conexión e inténtalo otra vez.</p>
            <button
                type="button"
                class="mt-4 min-h-11 rounded-xl bg-white px-4 py-2 font-semibold text-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-active"
                data-session-retry
            >
                Reintentar
            </button>
        </div>

        <nav class="space-y-6" aria-label="Secciones del panel" data-sidebar-navigation hidden></nav>
    </div>

    <div class="shrink-0 border-t border-white/10 p-3">
        <button
            type="button"
            class="flex min-h-11 w-full items-center gap-3 rounded-[14px] pe-3 text-sm font-semibold text-white/80 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-active"
            data-logout
            data-sidebar-logout
            data-nav-tooltip="Cerrar sesión"
            hidden
        >
            <span class="grid size-11 shrink-0 place-items-center" aria-hidden="true">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </span>
            <span class="shell-sidebar-label" data-logout-label>Cerrar sesión</span>
            <i class="fa-solid fa-circle-notch fa-spin ms-auto" data-logout-spinner aria-hidden="true" hidden></i>
        </button>
    </div>
</aside>
