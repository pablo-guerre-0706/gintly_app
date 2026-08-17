<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gintly | Inventario lógico y bodega física</title>

    <!-- =========================================================
         TAILWIND CSS
    ========================================================== -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',

            theme: {
                extend: {
                    colors: {
                        gintly: {
                            sidebar: '#073640',
                            sidebarHover: '#0a4652',
                            sidebarBorder: '#2a606a',

                            primary: '#087d98',
                            primaryDark: '#066b82',

                            page: '#f4f4f4',

                            green: '#19a15f',
                            greenSoft: '#e5f8ed',

                            orange: '#f28c28',
                            orangeSoft: '#fff0df',

                            red: '#df3838',
                            redSoft: '#ffe8e8'
                        }
                    },

                    fontFamily: {
                        sans: [
                            'Inter',
                            'ui-sans-serif',
                            'system-ui',
                            '-apple-system',
                            'BlinkMacSystemFont',
                            '"Segoe UI"',
                            'sans-serif'
                        ]
                    },

                    boxShadow: {
                        card: '0 1px 3px rgba(0,0,0,.05)',
                        drawer: '-12px 0 45px rgba(0,0,0,.15)'
                    }
                }
            }
        };
    </script>

    <!-- =========================================================
         ICONOS
    ========================================================== -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        .gintly-scrollbar::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }

        .gintly-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .gintly-scrollbar::-webkit-scrollbar-thumb {
            background: #bfc3c5;
            border-radius: 9999px;
        }

        .inventory-filter[aria-pressed="true"] {
            background: #087d98;
            border-color: #087d98;
            color: white;
        }

        .inventory-row {
            transition: background-color .12s ease;
        }

        .inventory-row:hover {
            background: #fafafa;
        }

        .dark .inventory-row:hover {
            background: #182231;
        }

        .loading-spinner {
            width: 18px;
            height: 18px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 9999px;
            animation: spin .7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 1279px) {
            #app-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 70;
                transform: translateX(-100%);
                transition: transform .18s ease;
            }

            #app-sidebar[data-open="true"] {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body
    class="bg-gintly-page font-sans text-[#202020] antialiased dark:bg-slate-950 dark:text-slate-100"
>

<div class="min-h-screen xl:flex">

    <!-- =========================================================
         SIDEBAR
    ========================================================== -->
    <aside
        id="app-sidebar"
        data-open="false"
        class="flex min-h-screen w-[220px] shrink-0 flex-col bg-gintly-sidebar text-white xl:sticky xl:top-0 xl:h-screen"
    >
        <!-- Logo -->
        <div
            class="flex h-[72px] items-center justify-between border-b border-gintly-sidebarBorder px-[22px]"
        >
            <a
                href="#"
                class="flex items-center gap-3"
                aria-label="Gintly"
            >
                <i
                    data-lucide="layers-3"
                    class="h-[24px] w-[24px]"
                ></i>
            </a>

            <button
                id="sidebar-collapse-btn"
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition hover:bg-white/10"
                aria-label="Cerrar menú"
            >
                <i
                    data-lucide="panel-left-close"
                    class="h-[19px] w-[19px]"
                ></i>
            </button>
        </div>

        <!-- Navegación -->
        <nav
            class="gintly-scrollbar flex-1 overflow-y-auto px-[20px] py-[28px]"
        >
            <section>
                <h2
                    class="mb-[18px] text-[15px] font-semibold text-slate-200"
                >
                    General
                </h2>

                <div class="space-y-[3px]">
                    <a
                        href="#"
                        class="flex min-h-[44px] items-center gap-[11px] rounded-lg px-1 text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="layout-dashboard"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Dasboard</span>
                    </a>

                    <button
                        type="button"
                        class="flex min-h-[44px] w-full items-center justify-between rounded-lg px-1 text-left text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[11px]">
                            <i
                                data-lucide="chart-no-axes-column-increasing"
                                class="h-[16px] w-[16px]"
                            ></i>

                            <span>Ventas y operaciones</span>
                        </span>

                        <i
                            data-lucide="chevron-down"
                            class="h-[14px] w-[14px]"
                        ></i>
                    </button>

                    <button
                        type="button"
                        class="flex min-h-[44px] w-full items-center justify-between rounded-lg bg-white/5 px-1 text-left text-[13px] text-white"
                    >
                        <span class="flex items-center gap-[11px]">
                            <i
                                data-lucide="archive"
                                class="h-[16px] w-[16px]"
                            ></i>

                            <span>Inventario y bodega</span>
                        </span>

                        <i
                            data-lucide="chevron-up"
                            class="h-[14px] w-[14px]"
                        ></i>
                    </button>

                    <a
                        href="#"
                        class="ml-[27px] flex min-h-[36px] items-center rounded-md px-2 text-[12px] font-medium text-cyan-100"
                    >
                        Inventario lógico
                    </a>

                    <button
                        type="button"
                        class="flex min-h-[44px] w-full items-center justify-between rounded-lg px-1 text-left text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[11px]">
                            <i
                                data-lucide="truck"
                                class="h-[16px] w-[16px]"
                            ></i>

                            <span>Compras y proveedores</span>
                        </span>

                        <i
                            data-lucide="chevron-down"
                            class="h-[14px] w-[14px]"
                        ></i>
                    </button>

                    <button
                        type="button"
                        class="flex min-h-[44px] w-full items-center justify-between rounded-lg px-1 text-left text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[11px]">
                            <i
                                data-lucide="hand-coins"
                                class="h-[16px] w-[16px]"
                            ></i>

                            <span>Finanzas y créditos</span>
                        </span>

                        <i
                            data-lucide="chevron-down"
                            class="h-[14px] w-[14px]"
                        ></i>
                    </button>

                    <a
                        href="#"
                        class="flex min-h-[44px] items-center gap-[11px] rounded-lg px-1 text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="user-round"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Personal</span>
                    </a>

                    <a
                        href="#"
                        class="flex min-h-[44px] items-center gap-[11px] rounded-lg px-1 text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="file-chart-column"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Reportes y analítica</span>
                    </a>
                </div>
            </section>

            <div
                class="my-[25px] border-t border-gintly-sidebarBorder"
            ></div>

            <section>
                <h2
                    class="mb-[17px] text-[15px] font-semibold text-slate-200"
                >
                    Herramientas
                </h2>

                <div class="space-y-[3px]">
                    <a
                        href="#"
                        class="flex min-h-[44px] items-center gap-[11px] rounded-lg px-1 text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="shield-alert"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Centro de Alertas</span>
                    </a>

                    <a
                        href="#"
                        class="flex min-h-[44px] items-center gap-[11px] rounded-lg px-1 text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="settings"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Configuración</span>
                    </a>

                    <div
                        class="flex min-h-[44px] items-center justify-between px-1"
                    >
                        <div
                            class="flex items-center gap-[11px] text-[13px]"
                        >
                            <i
                                data-lucide="moon"
                                class="h-[16px] w-[16px]"
                            ></i>

                            <span>Modo oscuro</span>
                        </div>

                        <button
                            id="dark-mode-toggle"
                            type="button"
                            role="switch"
                            aria-checked="false"
                            class="relative h-[21px] w-[38px] rounded-full border-2 border-white"
                        >
                            <span
                                id="dark-mode-knob"
                                class="absolute left-[2px] top-[2px] h-[13px] w-[13px] rounded-full bg-white transition-transform"
                            ></span>
                        </button>
                    </div>

                    <a
                        href="#"
                        class="flex min-h-[44px] items-center gap-[11px] rounded-lg px-1 text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="circle-help"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Centro de ayuda</span>
                    </a>
                </div>
            </section>
        </nav>

        <button
            id="logout-btn"
            type="button"
            class="mx-[20px] mb-[20px] flex min-h-[58px] items-center gap-[12px] border-t border-gintly-sidebarBorder pt-[20px] text-left text-[14px] font-semibold"
        >
            <i
                data-lucide="log-out"
                class="h-[18px] w-[18px]"
            ></i>

            <span>Salir de la cuenta</span>
        </button>
    </aside>

    <!-- Overlay móvil -->
    <button
        id="sidebar-overlay"
        type="button"
        class="fixed inset-0 z-[60] hidden bg-black/40 xl:hidden"
        aria-label="Cerrar menú"
    ></button>

    <!-- =========================================================
         ZONA DERECHA
    ========================================================== -->
    <div class="min-w-0 flex-1">

        <!-- =====================================================
             HEADER
        ====================================================== -->
        <header
            class="flex h-[72px] items-center justify-between border-b border-[#d4d4d4] bg-white px-[30px] dark:border-slate-700 dark:bg-slate-900"
        >
            <div
                class="flex min-w-0 items-center gap-[13px]"
            >
                <button
                    id="mobile-sidebar-btn"
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 xl:hidden"
                >
                    <i
                        data-lucide="menu"
                        class="h-5 w-5"
                    ></i>
                </button>

                <span
                    class="text-[15px] text-[#777777] dark:text-slate-400"
                >
                    Gintly
                </span>

                <i
                    data-lucide="chevron-right"
                    class="h-[16px] w-[16px] text-[#777777]"
                ></i>

                <strong
                    class="truncate text-[15px] font-semibold text-[#202020] dark:text-white"
                >
                    Inventario y bodega
                </strong>
            </div>

            <div
                class="flex items-center gap-[22px]"
            >
                <button
                    type="button"
                    class="relative flex h-[42px] w-[42px] items-center justify-center rounded-full bg-[#f4f4f4] text-[#666666] dark:bg-slate-800 dark:text-slate-300"
                    aria-label="Notificaciones"
                >
                    <i
                        data-lucide="bell"
                        class="h-[17px] w-[17px]"
                    ></i>

                    <span
                        class="absolute -right-1 -top-1 flex h-[17px] min-w-[17px] items-center justify-center rounded-full bg-red-600 px-1 text-[9px] font-bold text-white"
                    >
                        2
                    </span>
                </button>

                <button
                    type="button"
                    class="flex items-center gap-[14px]"
                >
                    <img
                        src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=96&h=96&q=85"
                        alt="Perfil"
                        class="h-[44px] w-[44px] rounded-full object-cover"
                    >

                    <i
                        data-lucide="chevrons-up-down"
                        class="h-[17px] w-[17px] text-[#777777]"
                    ></i>
                </button>
            </div>
        </header>

        <!-- =====================================================
             MAIN
        ====================================================== -->
        <main
            class="px-[27px] pb-[50px] pt-[32px] lg:px-[34px]"
        >
            <!-- Título -->
            <section>
                <h1
                    class="text-[31px] font-bold tracking-[-0.025em] text-black dark:text-white"
                >
                    Inventario lógico y bodega física
                </h1>

                <p
                    class="mt-[8px] text-[14px] text-[#777777] dark:text-slate-400"
                >
                    Control de existencias, reservas y niveles mínimos por bodega
                </p>
            </section>

            <!-- =================================================
                 BANNER DE ERROR GENERAL
            ================================================== -->
            <div
                id="inventory-error-banner"
                class="mt-[24px] hidden rounded-[12px] border border-red-200 bg-red-50 px-[18px] py-[14px] text-[14px] text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                role="alert"
            ></div>

            <!-- =================================================
                 KPIs
            ================================================== -->
            <section
                class="mt-[30px] grid grid-cols-1 gap-[18px] md:grid-cols-3"
            >
                <!-- Total saldos -->
                <article
                    class="flex min-h-[102px] items-center gap-[18px] rounded-[14px] border border-[#dddddd] bg-white px-[20px] shadow-card dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-[10px] bg-gintly-greenSoft text-gintly-green"
                    >
                        <i
                            data-lucide="boxes"
                            class="h-[29px] w-[29px]"
                        ></i>
                    </div>

                    <div>
                        <p
                            class="text-[13px] text-[#737373] dark:text-slate-400"
                        >
                            Saldos de inventario
                        </p>

                        <strong
                            id="metric-total-stock"
                            class="mt-1 block text-[27px] font-bold text-[#202020] dark:text-white"
                        >
                            0
                        </strong>

                        <span
                            class="text-[11px] text-[#8a8a8a]"
                        >
                            producto × bodega
                        </span>
                    </div>
                </article>

                <!-- Bajo mínimo -->
                <article
                    class="flex min-h-[102px] items-center gap-[18px] rounded-[14px] border border-[#dddddd] bg-white px-[20px] shadow-card dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-[10px] bg-gintly-orangeSoft text-gintly-orange"
                    >
                        <i
                            data-lucide="chart-no-axes-column-decreasing"
                            class="h-[29px] w-[29px]"
                        ></i>
                    </div>

                    <div>
                        <p
                            class="text-[13px] text-[#737373] dark:text-slate-400"
                        >
                            Bajo stock mínimo
                        </p>

                        <strong
                            id="metric-low-stock"
                            class="mt-1 block text-[27px] font-bold text-[#202020] dark:text-white"
                        >
                            0
                        </strong>

                        <span
                            class="text-[11px] text-[#8a8a8a]"
                        >
                            requieren atención
                        </span>
                    </div>
                </article>

                <!-- Movimientos -->
                <article
                    class="flex min-h-[102px] items-center gap-[18px] rounded-[14px] border border-[#dddddd] bg-white px-[20px] shadow-card dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-[10px] bg-gintly-redSoft text-gintly-red"
                    >
                        <i
                            data-lucide="arrow-right-left"
                            class="h-[29px] w-[29px]"
                        ></i>
                    </div>

                    <div>
                        <p
                            class="text-[13px] text-[#737373] dark:text-slate-400"
                        >
                            Movimientos de hoy
                        </p>

                        <strong
                            id="metric-movements"
                            class="mt-1 block text-[27px] font-bold text-[#202020] dark:text-white"
                        >
                            0
                        </strong>

                        <span
                            class="text-[11px] text-[#8a8a8a]"
                        >
                            entradas, salidas y ajustes
                        </span>
                    </div>
                </article>
            </section>

            <!-- Métrica horizontal -->
            <section
                class="mt-[18px] flex min-h-[86px] items-center gap-[18px] rounded-[14px] border border-[#dddddd] bg-white px-[20px] shadow-card dark:border-slate-700 dark:bg-slate-900"
            >
                <div
                    class="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-[9px] bg-gintly-redSoft text-gintly-red"
                >
                    <i
                        data-lucide="package-check"
                        class="h-[26px] w-[26px]"
                    ></i>
                </div>

                <div class="min-w-0 flex-1">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-[13px] font-medium text-[#313131] dark:text-slate-200"
                            >
                                Cantidad reservada en los registros visibles
                            </p>

                            <p
                                class="mt-1 text-[11px] text-[#8b8b8b]"
                            >
                                El disponible se calcula como cantidad − reservado
                            </p>
                        </div>

                        <strong
                            id="metric-reserved"
                            class="text-[24px] font-bold text-[#202020] dark:text-white"
                        >
                            0
                        </strong>
                    </div>
                </div>
            </section>

            <!-- =================================================
                 FILTROS
            ================================================== -->
            <section
                class="mt-[24px] rounded-[14px] border border-[#d8d8d8] bg-white p-[17px] shadow-card dark:border-slate-700 dark:bg-slate-900"
            >
                <div
                    class="flex flex-col gap-[13px] xl:flex-row xl:items-center"
                >
                    <!-- Search -->
                    <label
                        class="relative min-w-0 flex-1"
                    >
                        <span class="sr-only">
                            Buscar producto
                        </span>

                        <i
                            data-lucide="search"
                            class="pointer-events-none absolute left-[14px] top-1/2 h-[17px] w-[17px] -translate-y-1/2 text-[#777777]"
                        ></i>

                        <input
                            id="inventory-search"
                            type="search"
                            autocomplete="off"
                            placeholder="Buscar por SKU o producto"
                            class="h-[44px] w-full rounded-[9px] border border-[#c8c8c8] bg-white pl-[42px] pr-[15px] text-[13px] outline-none transition focus:border-gintly-primary focus:ring-2 focus:ring-gintly-primary/10 dark:border-slate-600 dark:bg-slate-950"
                        >
                    </label>

                    <!-- Filter buttons -->
                    <div
                        id="stock-filter-buttons"
                        class="flex items-center gap-[9px]"
                    >
                        <button
                            type="button"
                            data-stock-filter="all"
                            aria-pressed="true"
                            class="inventory-filter h-[42px] rounded-[9px] border border-gintly-primary bg-gintly-primary px-[18px] text-[12px] font-semibold text-white"
                        >
                            Todos
                        </button>

                        <button
                            type="button"
                            data-stock-filter="below"
                            aria-pressed="false"
                            class="inventory-filter h-[42px] rounded-[9px] border border-[#cccccc] bg-[#f7f7f7] px-[18px] text-[12px] font-medium text-[#656565] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            Stock bajo
                        </button>
                    </div>

                    <!-- Warehouse -->
                    <select
                        id="warehouse-filter"
                        class="h-[42px] min-w-[175px] rounded-[9px] border border-[#cccccc] bg-white px-[12px] text-[12px] text-[#5f5f5f] outline-none focus:border-gintly-primary dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300"
                    >
                        <option value="">
                            Todas las bodegas
                        </option>
                    </select>

                    <button
                        id="refresh-inventory-btn"
                        type="button"
                        class="inline-flex h-[42px] items-center justify-center gap-2 rounded-[9px] border border-[#cccccc] bg-white px-[14px] text-[12px] font-medium text-[#555555] transition hover:bg-[#f6f6f6] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <i
                            data-lucide="refresh-cw"
                            class="h-[15px] w-[15px]"
                        ></i>

                        Actualizar
                    </button>
                </div>
            </section>

            <!-- =================================================
                 TABLA
            ================================================== -->
            <section
                class="mt-[22px] overflow-hidden rounded-[14px] border border-[#d8d8d8] bg-white shadow-card dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Header tabla -->
                <div
                    class="flex min-h-[70px] flex-col gap-4 px-[20px] py-[15px] sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-[16px] font-semibold text-[#2a2a2a] dark:text-white"
                        >
                            Inventario lógico
                        </h2>

                        <p
                            id="inventory-record-count"
                            class="mt-1 text-[11px] text-[#838383]"
                        >
                            0 registros
                        </p>
                    </div>

                    <button
                        id="register-count-btn"
                        type="button"
                        class="inline-flex h-[42px] items-center justify-center gap-2 rounded-[9px] bg-gintly-primary px-[17px] text-[12px] font-semibold text-white transition hover:bg-gintly-primaryDark"
                    >
                        <i
                            data-lucide="clipboard-plus"
                            class="h-[16px] w-[16px]"
                        ></i>

                        Registrar conteo físico
                    </button>
                </div>

                <div
                    class="gintly-scrollbar overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[1050px] border-collapse"
                    >
                        <thead>
                        <tr
                            class="h-[54px] border-y border-[#dddddd] bg-[#f5f5f5] text-left dark:border-slate-700 dark:bg-slate-800"
                        >
                            <th
                                class="px-[20px] text-[11px] font-medium text-[#717171] dark:text-slate-300"
                            >
                                SKU
                            </th>

                            <th
                                class="px-[14px] text-[11px] font-medium text-[#717171] dark:text-slate-300"
                            >
                                Producto
                            </th>

                            <th
                                class="px-[14px] text-[11px] font-medium text-[#717171] dark:text-slate-300"
                            >
                                Bodega / ubicación
                            </th>

                            <th
                                class="px-[14px] text-[11px] font-medium text-[#717171] dark:text-slate-300"
                            >
                                Existencia
                            </th>

                            <th
                                class="px-[14px] text-[11px] font-medium text-[#717171] dark:text-slate-300"
                            >
                                Reservado
                            </th>

                            <th
                                class="px-[14px] text-[11px] font-medium text-[#717171] dark:text-slate-300"
                            >
                                Disponible
                            </th>

                            <th
                                class="px-[14px] text-[11px] font-medium text-[#717171] dark:text-slate-300"
                            >
                                Stock mínimo
                            </th>

                            <th
                                class="px-[14px] text-[11px] font-medium text-[#717171] dark:text-slate-300"
                            >
                                Estado
                            </th>

                            <th
                                class="px-[14px] text-[11px] font-medium text-[#717171] dark:text-slate-300"
                            >
                                Acción
                            </th>
                        </tr>
                        </thead>

                        <tbody
                            id="inventory-table-body"
                            aria-live="polite"
                        ></tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    id="pagination-container"
                    class="hidden min-h-[62px] items-center justify-between border-t border-[#dddddd] px-[20px] dark:border-slate-700"
                >
                    <span
                        id="pagination-label"
                        class="text-[11px] text-[#737373] dark:text-slate-400"
                    ></span>

                    <div class="flex gap-2">
                        <button
                            id="prev-page-btn"
                            type="button"
                            class="h-[34px] rounded-[7px] border border-[#cccccc] px-[12px] text-[11px] disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600"
                        >
                            Anterior
                        </button>

                        <button
                            id="next-page-btn"
                            type="button"
                            class="h-[34px] rounded-[7px] border border-[#cccccc] px-[12px] text-[11px] disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600"
                        >
                            Siguiente
                        </button>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>
<!-- =============================================================
     DRAWER OVERLAY
============================================================= -->
<div
    id="inventory-drawer-overlay"
    class="fixed inset-0 z-[100] hidden bg-black/45 opacity-0 transition-opacity duration-200"
></div>

<!-- =============================================================
     DRAWER
============================================================= -->
<aside
    id="inventory-drawer"
    class="fixed bottom-0 right-0 top-0 z-[110] w-full max-w-[440px] translate-x-full overflow-hidden bg-white shadow-drawer transition-transform duration-200 dark:bg-slate-900"
    role="dialog"
    aria-modal="true"
    aria-labelledby="drawer-title"
>
    <form
        id="inventory-drawer-form"
        class="flex h-full flex-col"
        novalidate
    >
        <!-- Header -->
        <div
            class="flex items-start justify-between border-b border-[#e1e1e1] px-[26px] py-[24px] dark:border-slate-700"
        >
            <div>
                <p
                    id="drawer-eyebrow"
                    class="text-[12px] text-[#7b7b7b] dark:text-slate-400"
                >
                    Inventario
                </p>

                <h2
                    id="drawer-title"
                    class="mt-[5px] text-[21px] font-bold text-[#202020] dark:text-white"
                >
                    Registrar conteo físico
                </h2>
            </div>

            <button
                id="close-drawer-btn"
                type="button"
                class="flex h-[38px] w-[38px] items-center justify-center rounded-full bg-[#f3f3f3] text-[#666666] transition hover:bg-[#e8e8e8] dark:bg-slate-800 dark:text-slate-300"
                aria-label="Cerrar"
            >
                <i
                    data-lucide="x"
                    class="h-[20px] w-[20px]"
                ></i>
            </button>
        </div>

        <!-- Body -->
        <div
            class="gintly-scrollbar flex-1 overflow-y-auto px-[26px] py-[25px]"
        >
            <!-- Error general -->
            <div
                id="drawer-global-error"
                class="mb-[20px] hidden rounded-[8px] border border-red-300 bg-red-100 px-[13px] py-[11px] text-[12px] text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                role="alert"
            ></div>

            <!-- ================================================
                 MODO: CONTEO FÍSICO
            ================================================= -->
            <div
                id="physical-count-fields"
            >
                <div
                    class="grid grid-cols-1 gap-[18px] sm:grid-cols-2"
                >
                    <!-- Product -->
                    <div>
                        <label
                            for="count-product-id"
                            class="mb-[7px] block text-[12px] font-medium text-[#444444] dark:text-slate-300"
                        >
                            Producto
                        </label>

                        <select
                            id="count-product-id"
                            name="product_id"
                            class="h-[44px] w-full rounded-[7px] border border-[#c9c9c9] bg-white px-[11px] text-[12px] outline-none focus:border-gintly-primary dark:border-slate-600 dark:bg-slate-950"
                        >
                            <option value="">
                                Seleccione
                            </option>
                        </select>

                        <p
                            data-drawer-error="product_id"
                            class="mt-[6px] hidden text-[11px] text-red-600"
                        ></p>
                    </div>

                    <!-- Warehouse -->
                    <div>
                        <label
                            for="count-warehouse-id"
                            class="mb-[7px] block text-[12px] font-medium text-[#444444] dark:text-slate-300"
                        >
                            Bodega
                        </label>

                        <select
                            id="count-warehouse-id"
                            name="warehouse_id"
                            class="h-[44px] w-full rounded-[7px] border border-[#c9c9c9] bg-white px-[11px] text-[12px] outline-none focus:border-gintly-primary dark:border-slate-600 dark:bg-slate-950"
                        >
                            <option value="">
                                Seleccione
                            </option>
                        </select>

                        <p
                            data-drawer-error="warehouse_id"
                            class="mt-[6px] hidden text-[11px] text-red-600"
                        ></p>
                    </div>
                </div>

                <div
                    class="mt-[19px]"
                >
                    <label
                        for="counted-quantity"
                        class="mb-[7px] block text-[12px] font-medium text-[#444444] dark:text-slate-300"
                    >
                        Cantidad contada
                    </label>

                    <input
                        id="counted-quantity"
                        name="counted_quantity"
                        type="text"
                        inputmode="decimal"
                        autocomplete="off"
                        placeholder="0.000"
                        class="h-[44px] w-full rounded-[7px] border border-[#c9c9c9] bg-white px-[11px] text-[12px] outline-none focus:border-gintly-primary dark:border-slate-600 dark:bg-slate-950"
                    >

                    <p
                        data-drawer-error="counted_quantity"
                        class="mt-[6px] hidden text-[11px] text-red-600"
                    ></p>
                </div>

                <div
                    class="mt-[19px]"
                >
                    <label
                        for="count-notes"
                        class="mb-[7px] block text-[12px] font-medium text-[#444444] dark:text-slate-300"
                    >
                        Notas
                    </label>

                    <textarea
                        id="count-notes"
                        name="notes"
                        maxlength="500"
                        rows="4"
                        placeholder="Observaciones del conteo físico"
                        class="w-full resize-none rounded-[7px] border border-[#c9c9c9] bg-white px-[11px] py-[10px] text-[12px] outline-none focus:border-gintly-primary dark:border-slate-600 dark:bg-slate-950"
                    ></textarea>

                    <p
                        data-drawer-error="notes"
                        class="mt-[6px] hidden text-[11px] text-red-600"
                    ></p>
                </div>

                <div
                    class="mt-[22px] rounded-[8px] border border-red-200 bg-red-50 px-[13px] py-[11px] text-[11px] leading-[1.55] text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                >
                    <div class="flex gap-[9px]">
                        <i
                            data-lucide="triangle-alert"
                            class="mt-[1px] h-[16px] w-[16px] shrink-0"
                        ></i>

                        <p>
                            Registrar un conteo físico no modifica directamente el saldo.
                            El backend captura la cantidad del sistema y calcula la diferencia.
                            El ajuste posterior requiere la acción de aplicar conteo.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ================================================
                 MODO: EDITAR UMBRALES
            ================================================= -->
            <div
                id="threshold-fields"
                class="hidden"
            >
                <div
                    class="grid grid-cols-1 gap-[13px] sm:grid-cols-2"
                >
                    <div
                        class="rounded-[9px] bg-[#f5f5f5] px-[13px] py-[12px] dark:bg-slate-800"
                    >
                        <span
                            class="block text-[10px] uppercase tracking-wide text-[#818181]"
                        >
                            Producto
                        </span>

                        <strong
                            id="threshold-product-name"
                            class="mt-[4px] block text-[12px] text-[#252525] dark:text-white"
                        >
                            —
                        </strong>
                    </div>

                    <div
                        class="rounded-[9px] bg-[#f5f5f5] px-[13px] py-[12px] dark:bg-slate-800"
                    >
                        <span
                            class="block text-[10px] uppercase tracking-wide text-[#818181]"
                        >
                            Bodega
                        </span>

                        <strong
                            id="threshold-warehouse-name"
                            class="mt-[4px] block text-[12px] text-[#252525] dark:text-white"
                        >
                            —
                        </strong>
                    </div>
                </div>

                <div
                    class="mt-[22px]"
                >
                    <label
                        for="threshold-min-stock"
                        class="mb-[7px] block text-[12px] font-medium text-[#444444] dark:text-slate-300"
                    >
                        Stock mínimo
                    </label>

                    <input
                        id="threshold-min-stock"
                        name="min_stock"
                        type="text"
                        inputmode="decimal"
                        autocomplete="off"
                        placeholder="0.000"
                        class="h-[44px] w-full rounded-[7px] border border-[#c9c9c9] bg-white px-[11px] text-[12px] outline-none focus:border-gintly-primary dark:border-slate-600 dark:bg-slate-950"
                    >

                    <p
                        data-drawer-error="min_stock"
                        class="mt-[6px] hidden text-[11px] text-red-600"
                    ></p>
                </div>

                <div
                    class="mt-[19px]"
                >
                    <label
                        for="threshold-max-stock"
                        class="mb-[7px] block text-[12px] font-medium text-[#444444] dark:text-slate-300"
                    >
                        Stock máximo
                    </label>

                    <input
                        id="threshold-max-stock"
                        name="max_stock"
                        type="text"
                        inputmode="decimal"
                        autocomplete="off"
                        placeholder="Opcional"
                        class="h-[44px] w-full rounded-[7px] border border-[#c9c9c9] bg-white px-[11px] text-[12px] outline-none focus:border-gintly-primary dark:border-slate-600 dark:bg-slate-950"
                    >

                    <p
                        data-drawer-error="max_stock"
                        class="mt-[6px] hidden text-[11px] text-red-600"
                    ></p>
                </div>

                <div
                    class="mt-[22px] rounded-[8px] border border-red-200 bg-red-50 px-[13px] py-[11px] text-[11px] leading-[1.55] text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                >
                    <div class="flex gap-[9px]">
                        <i
                            data-lucide="info"
                            class="mt-[1px] h-[16px] w-[16px] shrink-0"
                        ></i>

                        <p>
                            Solo pueden editarse los umbrales mínimo y máximo.
                            La existencia y la cantidad reservada son controladas exclusivamente
                            por el servicio de inventario.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer drawer -->
        <div
            class="flex items-center justify-end gap-[10px] border-t border-[#e1e1e1] px-[26px] py-[18px] dark:border-slate-700"
        >
            <button
                id="cancel-drawer-btn"
                type="button"
                class="h-[40px] rounded-[8px] bg-[#f0f0f0] px-[19px] text-[12px] font-medium text-[#555555] transition hover:bg-[#e7e7e7] dark:bg-slate-800 dark:text-slate-300"
            >
                Cancelar
            </button>

            <button
                id="save-drawer-btn"
                type="submit"
                class="inline-flex h-[40px] min-w-[105px] items-center justify-center gap-2 rounded-[8px] bg-gintly-primary px-[19px] text-[12px] font-semibold text-white transition hover:bg-gintly-primaryDark disabled:cursor-not-allowed disabled:opacity-60"
            >
                <i
                    data-lucide="save"
                    class="h-[15px] w-[15px]"
                ></i>

                <span>Guardar</span>
            </button>
        </div>
    </form>
</aside>

<!-- =============================================================
     CONFIG FRONTEND
============================================================= -->
<script>
    window.CONFIG_DEV = @json(config('app.debug'));
    window.GINTLY_API_BASE_URL = @json(url(''));
</script>

<script src="{{ asset('js/catalogo.js') }}"></script>

</body>
</html>
