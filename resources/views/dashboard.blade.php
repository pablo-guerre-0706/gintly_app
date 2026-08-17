<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gintly | Dashboard</title>

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
                            sidebarHover: '#0b4854',
                            sidebarBorder: '#2b6670',

                            primary: '#087d98',
                            primaryDark: '#066d86',

                            page: '#f3f3f3',

                            green: '#16a15d',
                            greenSoft: '#d9f3e3',

                            orange: '#ff861c',
                            orangeSoft: '#ffead3',

                            red: '#d71920',
                            redSoft: '#fbd9db',

                            blue: '#506dff',
                            blueSoft: '#dfe4ff'
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
                        card: '0 1px 3px rgba(0,0,0,.07)',
                        floating: '0 14px 40px rgba(0,0,0,.18)'
                    }
                }
            }
        };
    </script>

    <!-- =========================================================
         LUCIDE ICONS
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
            background: #bfc3c6;
            border-radius: 9999px;
        }

        .loading-spinner {
            width: 16px;
            height: 16px;
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

        .chart-legend-button {
            transition:
                opacity .15s ease,
                transform .15s ease;
        }

        .chart-legend-button:hover {
            transform: translateY(-1px);
        }

        .chart-legend-button[data-disabled="true"] {
            opacity: .35;
        }

        .cash-badge-open {
            border: 1px solid #35ba70;
            background: #e3f8eb;
            color: #15964f;
        }

        .cash-badge-blocked {
            border: 1px solid #f04e52;
            background: #fff0f0;
            color: #df2b30;
        }

        .cash-badge-closed {
            border: 1px solid #6b83ff;
            background: #eef0ff;
            color: #536cff;
        }

        .cash-badge-surplus {
            border: 1px solid #4d7bf3;
            background: #edf3ff;
            color: #3768e3;
        }

        .alert-severity-critical {
            border: 1px solid #ef4d4d;
            background: #fff0f0;
            color: #df3030;
        }

        .alert-severity-warning {
            border: 1px solid #ff9a37;
            background: #fff4e8;
            color: #e77b15;
        }

        .alert-severity-info {
            border: 1px solid #7190ff;
            background: #eff2ff;
            color: #516eea;
        }

        .dashboard-card {
            background: #ffffff;
            border: 1px solid #d7d7d7;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }

        .dark .dashboard-card {
            background: #0f172a;
            border-color: #334155;
        }

        @media (max-width: 1279px) {
            #app-sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                z-index: 80;
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
    class="bg-gintly-page font-sans text-[#1f1f1f] antialiased dark:bg-slate-950 dark:text-slate-100"
>

<div class="min-h-screen xl:flex">

    <!-- =========================================================
         SIDEBAR
    ========================================================== -->
    <aside
        id="app-sidebar"
        data-open="false"
        class="flex min-h-screen w-[280px] shrink-0 flex-col bg-gintly-sidebar text-white xl:sticky xl:top-0 xl:h-screen"
    >
        <!-- Logo -->
        <div
            class="flex h-[90px] items-center justify-between border-b border-gintly-sidebarBorder px-[38px]"
        >
            <a
                href="#"
                class="flex items-center"
                aria-label="Gintly"
            >
                <i
                    data-lucide="layers-3"
                    class="h-[25px] w-[25px]"
                ></i>
            </a>

            <button
                id="sidebar-collapse-btn"
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-lg text-white transition hover:bg-white/10"
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
            class="gintly-scrollbar flex-1 overflow-y-auto px-[38px] py-[30px]"
        >
            <section>
                <h2
                    class="mb-[20px] text-[15px] font-semibold text-slate-200"
                >
                    General
                </h2>

                <div class="space-y-[5px]">

                    <a
                        href="#"
                        class="flex min-h-[48px] items-center gap-[12px] rounded-lg bg-[#0d6070] px-[8px] text-[13px] text-white"
                    >
                        <i
                            data-lucide="layout-dashboard"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Dasboard</span>
                    </a>

                    <button
                        type="button"
                        class="flex min-h-[48px] w-full items-center justify-between rounded-lg px-[8px] text-left text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[12px]">
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
                        class="flex min-h-[48px] w-full items-center justify-between rounded-lg px-[8px] text-left text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[12px]">
                            <i
                                data-lucide="archive"
                                class="h-[16px] w-[16px]"
                            ></i>

                            <span>Inventario y bodega</span>
                        </span>

                        <i
                            data-lucide="chevron-down"
                            class="h-[14px] w-[14px]"
                        ></i>
                    </button>

                    <button
                        type="button"
                        class="flex min-h-[48px] w-full items-center justify-between rounded-lg px-[8px] text-left text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[12px]">
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
                        class="flex min-h-[48px] w-full items-center justify-between rounded-lg px-[8px] text-left text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[12px]">
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
                        class="flex min-h-[48px] items-center gap-[12px] rounded-lg px-[8px] text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="user-round"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Personal</span>
                    </a>

                    <a
                        href="#"
                        class="flex min-h-[48px] items-center gap-[12px] rounded-lg px-[8px] text-[13px] text-slate-100 transition hover:bg-white/5"
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
                class="my-[28px] border-t border-gintly-sidebarBorder"
            ></div>

            <section>
                <h2
                    class="mb-[18px] text-[15px] font-semibold text-slate-200"
                >
                    Herramientas
                </h2>

                <div class="space-y-[5px]">

                    <a
                        href="#"
                        class="flex min-h-[48px] items-center gap-[12px] rounded-lg px-[8px] text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="shield-alert"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Centro de Alertas y Anomalías</span>
                    </a>

                    <a
                        href="#"
                        class="flex min-h-[48px] items-center gap-[12px] rounded-lg px-[8px] text-[13px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="settings"
                            class="h-[16px] w-[16px]"
                        ></i>

                        <span>Configuración</span>
                    </a>

                    <div
                        class="flex min-h-[48px] items-center justify-between px-[8px]"
                    >
                        <div
                            class="flex items-center gap-[12px] text-[13px]"
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
                        class="flex min-h-[48px] items-center gap-[12px] rounded-lg px-[8px] text-[13px] text-slate-100 transition hover:bg-white/5"
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

        <!-- Logout -->
        <button
            id="logout-btn"
            type="button"
            class="mx-[38px] mb-[30px] flex min-h-[70px] items-center gap-[16px] border-t border-gintly-sidebarBorder pt-[24px] text-left text-[15px] font-semibold"
        >
            <i
                data-lucide="log-out"
                class="h-[19px] w-[19px]"
            ></i>

            <span>Salir de la cuenta</span>
        </button>
    </aside>

    <!-- Overlay móvil -->
    <button
        id="sidebar-overlay"
        type="button"
        class="fixed inset-0 z-[70] hidden bg-black/40 xl:hidden"
        aria-label="Cerrar menú"
    ></button>

    <!-- =========================================================
         CONTENIDO
    ========================================================== -->
    <div class="min-w-0 flex-1">

        <!-- =====================================================
             HEADER SUPERIOR
        ====================================================== -->
        <header
            class="flex min-h-[90px] items-center border-b border-[#d5d5d5] bg-white px-[30px] dark:border-slate-700 dark:bg-slate-900"
        >
            <div
                class="grid w-full grid-cols-[auto_minmax(220px,1fr)_auto] items-center gap-[30px]"
            >
                <!-- Breadcrumb -->
                <div
                    class="flex items-center gap-[12px]"
                >
                    <button
                        id="mobile-sidebar-btn"
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 xl:hidden"
                        aria-label="Abrir menú"
                    >
                        <i
                            data-lucide="menu"
                            class="h-5 w-5"
                        ></i>
                    </button>

                    <span
                        class="text-[14px] text-[#777777] dark:text-slate-400"
                    >
                        Gintly
                    </span>

                    <i
                        data-lucide="chevron-right"
                        class="h-[16px] w-[16px] text-[#777777]"
                    ></i>

                    <strong
                        class="text-[14px] font-semibold text-[#202020] dark:text-white"
                    >
                        Dashboard
                    </strong>
                </div>

                <!-- Search -->
                <label
                    class="relative mx-auto block w-full max-w-[640px]"
                >
                    <span class="sr-only">
                        Buscar en recursos
                    </span>

                    <i
                        data-lucide="search"
                        class="pointer-events-none absolute left-[17px] top-1/2 h-[17px] w-[17px] -translate-y-1/2 text-[#858585]"
                    ></i>

                    <input
                        id="global-search"
                        type="search"
                        autocomplete="off"
                        placeholder="Buscar en recursos..."
                        class="h-[44px] w-full rounded-full border-0 bg-[#f3f3f3] pl-[48px] pr-[18px] text-[12px] text-[#454545] outline-none transition focus:ring-2 focus:ring-gintly-primary/20 dark:bg-slate-800 dark:text-white"
                    >
                </label>

                <!-- Right -->
                <div
                    class="flex items-center gap-[22px]"
                >
                    <button
                        type="button"
                        class="relative flex h-[42px] w-[42px] items-center justify-center rounded-full bg-[#f5f5f5] text-[#777777] dark:bg-slate-800 dark:text-slate-300"
                        aria-label="Notificaciones"
                    >
                        <i
                            data-lucide="bell"
                            class="h-[17px] w-[17px]"
                        ></i>
                    </button>

                    <button
                        type="button"
                        class="flex items-center gap-[14px]"
                        aria-label="Perfil"
                    >
                        <img
                            src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=96&h=96&q=85"
                            alt="Perfil del usuario"
                            class="h-[48px] w-[48px] rounded-full object-cover"
                        >

                        <i
                            data-lucide="chevrons-up-down"
                            class="h-[17px] w-[17px] text-[#777777]"
                        ></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- =====================================================
             MAIN
        ====================================================== -->
        <main
            class="px-[28px] pb-[55px] pt-[38px] 2xl:px-[34px]"
        >
            <!-- =================================================
                 TÍTULO / CONTROLES
            ================================================== -->
            <section
                class="flex flex-col gap-[20px] lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <h1
                        class="text-[32px] font-bold tracking-[-0.025em] text-[#171717] dark:text-white"
                    >
                        Dashboard
                    </h1>

                    <p
                        id="dashboard-updated-at"
                        class="mt-[8px] text-[12px] text-[#7f7f7f] dark:text-slate-400"
                    >
                        Actualizando información...
                    </p>
                </div>

                <div
                    class="flex items-center gap-[13px]"
                >
                    <button
                        id="export-dashboard-btn"
                        type="button"
                        class="inline-flex h-[45px] items-center justify-center gap-[9px] rounded-[24px] border border-[#c8c8c8] bg-white px-[20px] text-[12px] font-medium text-[#555555] transition hover:bg-[#f8f8f8] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
                    >
                        <i
                            data-lucide="download"
                            class="h-[15px] w-[15px]"
                        ></i>

                        <span>Exportar</span>
                    </button>

                    <button
                        id="refresh-dashboard-btn"
                        type="button"
                        class="inline-flex h-[45px] min-w-[126px] items-center justify-center gap-[9px] rounded-[24px] bg-gintly-primary px-[20px] text-[12px] font-semibold text-white transition hover:bg-gintly-primaryDark disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <i
                            data-lucide="refresh-cw"
                            class="h-[15px] w-[15px]"
                        ></i>

                        <span>Actualizar</span>
                    </button>
                </div>
            </section>

            <!-- =================================================
                 KPIs PRINCIPALES
            ================================================== -->
            <section
                class="mt-[32px] grid grid-cols-1 gap-[22px] lg:grid-cols-3"
            >
                <!-- Ventas -->
                <article
                    class="dashboard-card flex min-h-[177px] items-center gap-[22px] px-[25px] py-[24px]"
                >
                    <div
                        class="flex h-[78px] w-[78px] shrink-0 items-center justify-center rounded-[12px] bg-gintly-greenSoft text-gintly-green"
                    >
                        <i
                            data-lucide="chart-no-axes-column-increasing"
                            class="h-[39px] w-[39px]"
                        ></i>
                    </div>

                    <div class="min-w-0">
                        <p
                            class="text-[16px] text-[#777777] dark:text-slate-400"
                        >
                            Ventas del día
                        </p>

                        <strong
                            id="kpi-sales-value"
                            class="mt-[5px] block text-[26px] font-bold text-[#161616] dark:text-white"
                        >
                            C$ 0.00
                        </strong>

                        <p
                            id="kpi-sales-detail"
                            class="mt-[4px] text-[11px] text-[#8b8b8b]"
                        >
                            0 transacciones
                        </p>

                        <p
                            id="kpi-sales-trend"
                            class="mt-[10px] text-[11px] font-semibold text-[#159d8a]"
                        >
                            —
                        </p>
                    </div>
                </article>

                <!-- Ticket -->
                <article
                    class="dashboard-card flex min-h-[177px] items-center gap-[22px] px-[25px] py-[24px]"
                >
                    <div
                        class="flex h-[78px] w-[78px] shrink-0 items-center justify-center rounded-[12px] bg-gintly-orangeSoft text-gintly-orange"
                    >
                        <i
                            data-lucide="receipt-text"
                            class="h-[39px] w-[39px]"
                        ></i>
                    </div>

                    <div class="min-w-0">
                        <p
                            class="text-[16px] text-[#777777] dark:text-slate-400"
                        >
                            Ticket promedio
                        </p>

                        <strong
                            id="kpi-ticket-value"
                            class="mt-[5px] block text-[26px] font-bold text-[#161616] dark:text-white"
                        >
                            C$ 0.00
                        </strong>

                        <p
                            id="kpi-ticket-detail"
                            class="mt-[4px] text-[11px] text-[#8b8b8b]"
                        >
                            0 transacciones
                        </p>

                        <p
                            id="kpi-ticket-trend"
                            class="mt-[10px] text-[11px] font-semibold text-[#159d8a]"
                        >
                            —
                        </p>
                    </div>
                </article>

                <!-- CxC -->
                <article
                    class="dashboard-card flex min-h-[177px] items-center gap-[22px] px-[25px] py-[24px]"
                >
                    <div
                        class="flex h-[78px] w-[78px] shrink-0 items-center justify-center rounded-[12px] bg-gintly-redSoft text-gintly-red"
                    >
                        <i
                            data-lucide="file-warning"
                            class="h-[39px] w-[39px]"
                        ></i>
                    </div>

                    <div class="min-w-0">
                        <p
                            class="text-[16px] text-[#777777] dark:text-slate-400"
                        >
                            CxC Vencida +60 días
                        </p>

                        <strong
                            id="kpi-overdue-value"
                            class="mt-[5px] block text-[26px] font-bold text-[#161616] dark:text-white"
                        >
                            C$ 0.00
                        </strong>

                        <p
                            id="kpi-overdue-detail"
                            class="mt-[4px] text-[11px] text-[#8b8b8b]"
                        >
                            C$ 0.00
                        </p>
                    </div>
                </article>
            </section>

            <!-- =================================================
                 KPI SECUNDARIO SUPERIOR
            ================================================== -->
            <section
                class="mt-[22px] grid grid-cols-1 gap-[22px] lg:grid-cols-2"
            >
                <!-- Descuadre -->
                <article
                    class="dashboard-card flex min-h-[137px] items-center gap-[22px] px-[25px] py-[22px]"
                >
                    <div
                        class="flex h-[68px] w-[68px] shrink-0 items-center justify-center rounded-[11px] bg-gintly-orangeSoft text-gintly-orange"
                    >
                        <i
                            data-lucide="bell-ring"
                            class="h-[34px] w-[34px]"
                        ></i>
                    </div>

                    <div>
                        <p
                            class="text-[15px] text-[#777777] dark:text-slate-400"
                        >
                            Descuadre Inv.
                        </p>

                        <strong
                            id="kpi-inventory-mismatch-value"
                            class="mt-[4px] block text-[25px] font-bold text-[#202020] dark:text-white"
                        >
                            0 C$
                        </strong>

                        <p
                            id="kpi-inventory-mismatch-detail"
                            class="mt-[4px] text-[11px] text-[#8a8a8a]"
                        >
                            —
                        </p>
                    </div>
                </article>

                <!-- Alertas -->
                <article
                    class="dashboard-card flex min-h-[137px] items-center gap-[22px] px-[25px] py-[22px]"
                >
                    <div
                        class="flex h-[68px] w-[68px] shrink-0 items-center justify-center rounded-[11px] bg-gintly-redSoft text-gintly-red"
                    >
                        <i
                            data-lucide="chart-no-axes-column"
                            class="h-[34px] w-[34px]"
                        ></i>
                    </div>

                    <div>
                        <p
                            class="text-[15px] text-[#777777] dark:text-slate-400"
                        >
                            Alertas activas
                        </p>

                        <strong
                            id="kpi-active-alerts-value"
                            class="mt-[4px] block text-[25px] font-bold text-[#202020] dark:text-white"
                        >
                            0
                        </strong>

                        <p
                            id="kpi-active-alerts-detail"
                            class="mt-[4px] text-[11px] text-[#8a8a8a]"
                        >
                            —
                        </p>
                    </div>
                </article>
            </section>

            <!-- =================================================
                 GRÁFICO SEMANAL + CAJA
            ================================================== -->
            <section
                class="mt-[34px] grid grid-cols-1 gap-[24px] xl:grid-cols-[minmax(0,1.65fr)_minmax(330px,.9fr)]"
            >
                <!-- Ventas semanal -->
                <article
                    class="dashboard-card min-w-0 px-[24px] py-[24px]"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-[15px]"
                    >
                        <div>
                            <h2
                                class="text-[15px] font-bold text-[#222222] dark:text-white"
                            >
                                Estadísticas de ventas semanal
                            </h2>

                            <p
                                class="mt-[4px] text-[11px] text-[#777777]"
                            >
                                VS ventas diarias
                            </p>
                        </div>

                        <select
                            id="sales-currency-select"
                            class="h-[34px] rounded-full border-0 bg-[#f2f2f2] px-[14px] text-[10px] text-[#555555] outline-none dark:bg-slate-800 dark:text-slate-300"
                        >
                            <option value="NIO">
                                C$ · NIO
                            </option>

                            <option value="USD">
                                $ · USD
                            </option>
                        </select>
                    </div>

                    <!-- Leyenda -->
                    <div
                        id="sales-chart-legend"
                        class="mt-[16px] flex flex-wrap items-center gap-x-[18px] gap-y-[8px]"
                    >
                        <button
                            type="button"
                            data-sales-series="physical"
                            data-disabled="false"
                            class="chart-legend-button flex items-center gap-[6px] text-[10px] text-[#777777]"
                        >
                            <span class="h-[10px] w-[10px] bg-[#2ab968]"></span>
                            Venta física
                        </button>

                        <button
                            type="button"
                            data-sales-series="marketplace"
                            data-disabled="false"
                            class="chart-legend-button flex items-center gap-[6px] text-[10px] text-[#777777]"
                        >
                            <span class="h-[10px] w-[10px] bg-[#ff9a43]"></span>
                            Marketplace
                        </button>

                        <button
                            type="button"
                            data-sales-series="wholesale"
                            data-disabled="false"
                            class="chart-legend-button flex items-center gap-[6px] text-[10px] text-[#777777]"
                        >
                            <span class="h-[10px] w-[10px] bg-[#506dff]"></span>
                            Venta mayorista
                        </button>

                        <span
                            class="ml-auto flex items-center gap-[6px] text-[10px] text-[#65a976]"
                        >
                            <span class="h-[2px] w-[18px] bg-[#55ad71]"></span>
                            Meta actual
                        </span>
                    </div>

                    <div
                        class="mt-[18px] h-[360px] w-full"
                    >
                        <svg
                            id="sales-chart-svg"
                            viewBox="0 0 780 360"
                            preserveAspectRatio="none"
                            class="h-full w-full overflow-visible"
                            aria-label="Estadísticas de ventas semanal"
                        ></svg>
                    </div>
                </article>

                <!-- Caja -->
                <article
                    class="dashboard-card overflow-hidden"
                >
                    <div
                        class="flex min-h-[76px] items-center justify-between px-[24px]"
                    >
                        <h2
                            class="text-[15px] font-bold text-[#222222] dark:text-white"
                        >
                            Estado de caja: Encuadre
                        </h2>

                        <button
                            type="button"
                            class="flex h-[36px] w-[36px] items-center justify-center rounded-full bg-[#f3f3f3] text-[#777777] dark:bg-slate-800"
                            aria-label="Más opciones"
                        >
                            <i
                                data-lucide="ellipsis"
                                class="h-[17px] w-[17px]"
                            ></i>
                        </button>
                    </div>

                    <div
                        id="cash-status-list"
                        class="px-[24px] pb-[16px]"
                    ></div>
                </article>
            </section>

            <!-- =================================================
                 BLOQUE SECUNDARIO
            ================================================== -->
            <section
                class="mt-[38px] grid grid-cols-1 gap-[24px] xl:grid-cols-[.95fr_1.45fr_.8fr]"
            >
                <!-- Inventario -->
                <article
                    class="dashboard-card px-[22px] py-[22px]"
                >
                    <h2
                        class="text-[13px] font-bold text-[#242424] dark:text-white"
                    >
                        Inv. Lógico vs. Físico
                    </h2>

                    <p
                        class="mt-[5px] text-[10px] text-[#777777]"
                    >
                        Diferencia por sucursal (uds)
                    </p>

                    <div
                        class="mt-[18px] h-[330px]"
                    >
                        <svg
                            id="inventory-chart-svg"
                            viewBox="0 0 360 330"
                            preserveAspectRatio="none"
                            class="h-full w-full overflow-visible"
                            aria-label="Inventario lógico versus físico"
                        ></svg>
                    </div>

                    <div
                        class="mt-[12px] flex flex-wrap items-center gap-[15px] text-[9px] text-[#777777]"
                    >
                        <span class="flex items-center gap-[5px]">
                            <span class="h-[8px] w-[8px] bg-[#506dff]"></span>
                            Lógico
                        </span>

                        <span class="flex items-center gap-[5px]">
                            <span class="h-[8px] w-[8px] bg-[#1fad5d]"></span>
                            Físico
                        </span>

                        <span class="flex items-center gap-[5px]">
                            <span class="h-[8px] w-[8px] bg-[#e31e24]"></span>
                            Descuadre
                        </span>
                    </div>
                </article>

                <!-- CxC -->
                <article
                    class="dashboard-card px-[22px] py-[22px]"
                >
                    <div
                        class="flex items-start justify-between gap-[18px]"
                    >
                        <div>
                            <h2
                                class="text-[13px] font-bold text-[#242424] dark:text-white"
                            >
                                Exposición de cuentas por cobrar
                            </h2>

                            <p
                                class="mt-[5px] text-[10px] text-[#777777]"
                            >
                                Antigüedad de cartera · Últimos 5 meses
                            </p>
                        </div>

                        <div class="text-right">
                            <strong
                                id="receivables-total-value"
                                class="text-[18px] font-bold text-[#222222] dark:text-white"
                            >
                                C$ 0.00
                            </strong>

                            <span
                                class="mt-[3px] block text-[9px] text-[#888888]"
                            >
                                Total cartera activa
                            </span>
                        </div>
                    </div>

                    <div
                        class="mt-[13px] flex flex-wrap gap-[14px] text-[9px] text-[#777777]"
                    >
                        <span class="flex items-center gap-[5px]">
                            <span class="h-[9px] w-[9px] bg-[#17a95d]"></span>
                            Corriente
                        </span>

                        <span class="flex items-center gap-[5px]">
                            <span class="h-[9px] w-[9px] bg-[#506dff]"></span>
                            1-30 días
                        </span>

                        <span class="flex items-center gap-[5px]">
                            <span class="h-[9px] w-[9px] bg-[#ff8a25]"></span>
                            31-60 días
                        </span>

                        <span class="flex items-center gap-[5px]">
                            <span class="h-[9px] w-[9px] bg-[#dc1f26]"></span>
                            +60 días
                        </span>
                    </div>

                    <div
                        class="mt-[15px] h-[300px]"
                    >
                        <svg
                            id="receivables-chart-svg"
                            viewBox="0 0 560 300"
                            preserveAspectRatio="none"
                            class="h-full w-full overflow-visible"
                            aria-label="Exposición de cuentas por cobrar"
                        ></svg>
                    </div>
                </article>

                <!-- Mini KPIs -->
                <div
                    class="grid grid-cols-1 gap-[22px]"
                >
                    <article
                        class="dashboard-card flex min-h-[140px] flex-col justify-center px-[22px]"
                    >
                        <p
                            class="text-[12px] text-[#7b7b7b]"
                        >
                            Margen bruto
                        </p>

                        <strong
                            id="mini-gross-margin"
                            class="mt-[8px] text-[20px] font-bold text-[#13a35b]"
                        >
                            C$ 0.00
                        </strong>

                        <span
                            id="mini-gross-margin-trend"
                            class="mt-[8px] text-[10px] font-semibold text-[#159b8d]"
                        >
                            —
                        </span>
                    </article>

                    <article
                        class="dashboard-card flex min-h-[140px] flex-col justify-center px-[22px]"
                    >
                        <p
                            class="text-[12px] text-[#7b7b7b]"
                        >
                            Devoluciones
                        </p>

                        <strong
                            id="mini-returns"
                            class="mt-[8px] text-[20px] font-bold text-[#f07815]"
                        >
                            C$ 0.00
                        </strong>

                        <span
                            id="mini-returns-detail"
                            class="mt-[8px] text-[10px] font-semibold text-[#188faa]"
                        >
                            —
                        </span>
                    </article>

                    <article
                        class="dashboard-card flex min-h-[140px] flex-col justify-center px-[22px]"
                    >
                        <p
                            class="text-[12px] text-[#7b7b7b]"
                        >
                            Compras en aprobación
                        </p>

                        <strong
                            id="mini-purchase-orders"
                            class="mt-[8px] text-[20px] font-bold text-[#df2429]"
                        >
                            0 OC
                        </strong>

                        <span
                            id="mini-purchase-orders-detail"
                            class="mt-[8px] text-[10px] font-semibold text-[#188faa]"
                        >
                            —
                        </span>
                    </article>
                </div>
            </section>

            <!-- =================================================
                 ALERTAS
            ================================================== -->
            <section
                class="dashboard-card mt-[38px] overflow-hidden"
            >
                <div
                    class="flex flex-col gap-[12px] border-b border-[#e1e1e1] px-[24px] py-[20px] lg:flex-row lg:items-center lg:justify-between dark:border-slate-700"
                >
                    <div
                        class="flex items-center gap-[12px]"
                    >
                        <div
                            class="flex h-[40px] w-[40px] items-center justify-center rounded-[9px] bg-gintly-redSoft text-gintly-red"
                        >
                            <i
                                data-lucide="triangle-alert"
                                class="h-[21px] w-[21px]"
                            ></i>
                        </div>

                        <div>
                            <div
                                class="flex items-center gap-[9px]"
                            >
                                <h2
                                    class="text-[14px] font-bold text-[#242424] dark:text-white"
                                >
                                    Centro de Alertas de Anomalías
                                </h2>

                                <span
                                    id="alerts-header-count"
                                    class="flex h-[20px] min-w-[20px] items-center justify-center rounded-full bg-red-600 px-[5px] text-[9px] font-bold text-white"
                                >
                                    0
                                </span>
                            </div>

                            <p
                                class="mt-[3px] text-[9px] text-[#8a8a8a]"
                            >
                                Motor antifraude activo
                            </p>
                        </div>
                    </div>

                    <div
                        id="alerts-status-summary"
                        class="text-[9px] uppercase tracking-wide text-[#8a8a8a]"
                    >
                        —
                    </div>
                </div>

                <div
                    id="alerts-list"
                    class="px-[24px] pb-[12px]"
                ></div>
            </section>
        </main>
    </div>
</div>

<!-- =============================================================
     ERROR FLOATING BANNER
============================================================= -->
<div
    id="dashboard-error-toast"
    class="fixed right-[24px] top-[24px] z-[150] hidden w-[min(430px,calc(100vw-48px))] rounded-[12px] border border-red-300 bg-red-50 px-[17px] py-[14px] text-red-700 shadow-floating dark:border-red-900 dark:bg-red-950 dark:text-red-300"
    role="alert"
>
    <div class="flex items-start gap-[11px]">
        <i
            data-lucide="circle-alert"
            class="mt-[1px] h-[19px] w-[19px] shrink-0"
        ></i>

        <div class="min-w-0 flex-1">
            <strong
                id="dashboard-error-title"
                class="block text-[12px] font-bold"
            >
                Error de sincronización
            </strong>

            <p
                id="dashboard-error-message"
                class="mt-[3px] text-[11px]"
            ></p>
        </div>

        <button
            id="close-error-toast-btn"
            type="button"
            class="flex h-7 w-7 items-center justify-center rounded-full hover:bg-red-100 dark:hover:bg-red-900"
            aria-label="Cerrar error"
        >
            <i
                data-lucide="x"
                class="h-[15px] w-[15px]"
            ></i>
        </button>
    </div>
</div>

<!-- =============================================================
     CONFIG
============================================================= -->
<script>
    window.CONFIG_DEV = @json(config('app.debug'));

    window.GINTLY_API_BASE_URL = @json(url(''));

    /*
     * La documentación actual todavía no define DashboardController.
     * Esta URL es el contrato agregador que consumirá el frontend
     * cuando dicho controlador sea implementado.
     */
    window.GINTLY_DASHBOARD_API_URL = @json(
        config('gintly.dashboard.endpoint', url('/api/v1/dashboard'))
    );
</script>

<script src="{{ asset('js/dashboard.js') }}"></script>

</body>
</html>
