<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gintly | Catálogo y datos maestros</title>

    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',

            theme: {
                extend: {
                    colors: {
                        gintly: {
                            sidebar: '#07333d',
                            sidebarBorder: '#2e6570',
                            primary: '#087d98',
                            primaryDark: '#066d85',
                            page: '#f3f3f3',
                            border: '#d1d1d1'
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
                        card: '0 1px 2px rgba(0,0,0,.03)',
                        modal: '0 24px 80px rgba(0,0,0,.28)'
                    }
                }
            }
        };
    </script>

    <!-- Lucide -->
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
            background: #c7c7c7;
            border-radius: 9999px;
        }

        .sidebar-transition {
            transition:
                width 180ms ease,
                transform 180ms ease;
        }

        .category-chip {
            transition:
                background-color 120ms ease,
                color 120ms ease,
                border-color 120ms ease;
        }

        .category-chip[aria-pressed="true"] {
            background: #087d98;
            border-color: #087d98;
            color: #ffffff;
        }

        .dark .category-chip[aria-pressed="false"] {
            background: #1f2937;
            border-color: #4b5563;
            color: #d1d5db;
        }

        .table-row-hover {
            transition: background-color 100ms ease;
        }

        .table-row-hover:hover {
            background-color: #fafafa;
        }

        .dark .table-row-hover:hover {
            background-color: #172033;
        }

        .loading-spinner {
            width: 18px;
            height: 18px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 9999px;
            animation: spin .75s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 1279px) {
            #app-sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                z-index: 60;
                transform: translateX(-100%);
            }

            #app-sidebar[data-open="true"] {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body
    class="bg-gintly-page font-sans text-[#161616] antialiased dark:bg-slate-950 dark:text-slate-100"
>

<div class="min-h-screen xl:flex">

    <!-- =========================================================
         SIDEBAR
    ========================================================== -->
    <aside
        id="app-sidebar"
        data-open="false"
        class="sidebar-transition flex min-h-screen w-[455px] max-w-[86vw] shrink-0 flex-col bg-gintly-sidebar text-slate-100 xl:sticky xl:top-0 xl:h-screen xl:w-[455px]"
    >
        <!-- Logo / cabecera -->
        <div
            class="flex h-[118px] items-center justify-between border-b border-gintly-sidebarBorder px-[64px]"
        >
            <a
                href="#"
                class="flex items-center gap-3"
                aria-label="Gintly"
            >
                <div class="flex h-9 w-9 items-center justify-center">
                    <i
                        data-lucide="layers-3"
                        class="h-[27px] w-[27px] stroke-[2.1]"
                    ></i>
                </div>
            </a>

            <button
                id="sidebar-collapse-btn"
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-100 transition hover:bg-white/10"
                aria-label="Contraer menú"
            >
                <i
                    data-lucide="panel-left-close"
                    class="h-[23px] w-[23px]"
                ></i>
            </button>
        </div>

        <nav
            class="gintly-scrollbar flex-1 overflow-y-auto px-[64px] py-[38px]"
        >
            <!-- General -->
            <section>
                <h2
                    class="mb-[31px] text-[22px] font-semibold tracking-[-0.01em] text-slate-200"
                >
                    General
                </h2>

                <div class="space-y-[10px]">

                    <a
                        href="#"
                        class="flex min-h-[66px] items-center gap-[14px] rounded-lg text-[17px] font-normal text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="layout-dashboard"
                            class="h-[18px] w-[18px]"
                        ></i>

                        <span>Dasboard</span>
                    </a>

                    <button
                        type="button"
                        class="flex min-h-[66px] w-full items-center justify-between rounded-lg text-left text-[17px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[14px]">
                            <i
                                data-lucide="chart-no-axes-column-increasing"
                                class="h-[18px] w-[18px]"
                            ></i>

                            <span>Ventas y operaciones</span>
                        </span>

                        <i
                            data-lucide="chevron-down"
                            class="h-[17px] w-[17px]"
                        ></i>
                    </button>

                    <button
                        type="button"
                        class="flex min-h-[66px] w-full items-center justify-between rounded-lg text-left text-[17px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[14px]">
                            <i
                                data-lucide="archive"
                                class="h-[18px] w-[18px]"
                            ></i>

                            <span>Inventario y bodega</span>
                        </span>

                        <i
                            data-lucide="chevron-down"
                            class="h-[17px] w-[17px]"
                        ></i>
                    </button>

                    <button
                        type="button"
                        class="flex min-h-[66px] w-full items-center justify-between rounded-lg text-left text-[17px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[14px]">
                            <i
                                data-lucide="truck"
                                class="h-[18px] w-[18px]"
                            ></i>

                            <span>Compras y proveedores</span>
                        </span>

                        <i
                            data-lucide="chevron-down"
                            class="h-[17px] w-[17px]"
                        ></i>
                    </button>

                    <button
                        type="button"
                        class="flex min-h-[66px] w-full items-center justify-between rounded-lg text-left text-[17px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span class="flex items-center gap-[14px]">
                            <i
                                data-lucide="hand-coins"
                                class="h-[18px] w-[18px]"
                            ></i>

                            <span>Finanzas y creditos</span>
                        </span>

                        <i
                            data-lucide="chevron-down"
                            class="h-[17px] w-[17px]"
                        ></i>
                    </button>

                    <a
                        href="#"
                        class="flex min-h-[66px] items-center gap-[14px] rounded-lg text-[17px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="user-round"
                            class="h-[18px] w-[18px]"
                        ></i>

                        <span>Personal</span>
                    </a>

                    <a
                        href="#"
                        class="flex min-h-[66px] items-center gap-[14px] rounded-lg text-[17px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="file"
                            class="h-[18px] w-[18px]"
                        ></i>

                        <span>Reportes y analítica</span>
                    </a>
                </div>
            </section>

            <div
                class="my-[32px] border-t border-gintly-sidebarBorder"
            ></div>

            <!-- Herramientas -->
            <section>
                <h2
                    class="mb-[28px] text-[22px] font-semibold text-slate-200"
                >
                    Herramientas
                </h2>

                <div class="space-y-[7px]">
                    <a
                        href="#"
                        class="flex min-h-[63px] items-center gap-[14px] rounded-lg text-[17px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="shield-alert"
                            class="h-[18px] w-[18px]"
                        ></i>

                        <span>Centro de Alertas y Anomalías</span>
                    </a>

                    <a
                        href="#"
                        class="flex min-h-[63px] items-center gap-[14px] rounded-lg text-[17px] text-slate-100 transition hover:bg-white/5"
                    >
                        <i
                            data-lucide="settings"
                            class="h-[18px] w-[18px]"
                        ></i>

                        <span>Configuración</span>
                    </a>

                    <div
                        class="flex min-h-[63px] items-center justify-between"
                    >
                        <div
                            class="flex items-center gap-[14px] text-[17px]"
                        >
                            <i
                                data-lucide="moon"
                                class="h-[18px] w-[18px]"
                            ></i>

                            <span>Modo oscuro</span>
                        </div>

                        <button
                            id="dark-mode-toggle"
                            type="button"
                            role="switch"
                            aria-checked="false"
                            class="relative h-[26px] w-[48px] rounded-full border-[3px] border-white bg-transparent transition"
                        >
                            <span
                                id="dark-mode-knob"
                                class="absolute left-[3px] top-[3px] h-[14px] w-[14px] rounded-full bg-white transition-transform"
                            ></span>
                        </button>
                    </div>

                    <a
                        href="#"
                        class="flex min-h-[63px] items-center gap-[14px] rounded-lg text-[17px] text-slate-100 transition hover:bg-white/5"
                    >
                        <span
                            class="flex h-[18px] w-[18px] items-center justify-center text-[16px]"
                        >
                            ?
                        </span>

                        <span>Centro de ayuda</span>
                    </a>
                </div>
            </section>

            <div
                class="mt-[26px] border-t border-gintly-sidebarBorder"
            ></div>
        </nav>

        <!-- Logout -->
        <button
            id="logout-btn"
            type="button"
            class="mx-[64px] mb-[43px] flex min-h-[70px] items-center gap-[25px] border-t border-gintly-sidebarBorder pt-[28px] text-left text-[20px] font-semibold text-white transition hover:text-slate-200"
        >
            <i
                data-lucide="log-out"
                class="h-[24px] w-[24px]"
            ></i>

            <span>Salir de la cuenta</span>
        </button>
    </aside>

    <!-- Overlay sidebar móvil -->
    <button
        id="sidebar-overlay"
        type="button"
        aria-label="Cerrar menú"
        class="fixed inset-0 z-50 hidden bg-black/40 xl:hidden"
    ></button>

    <!-- =========================================================
         CONTENIDO
    ========================================================== -->
    <div class="min-w-0 flex-1">

        <!-- Header superior -->
        <header
            class="flex h-[144px] items-center justify-between border-b border-[#cccccc] bg-white px-[44px] dark:border-slate-700 dark:bg-slate-900 xl:px-[45px]"
        >
            <div
                class="flex min-w-0 items-center gap-[19px]"
            >
                <button
                    id="mobile-sidebar-btn"
                    type="button"
                    class="mr-2 flex h-10 w-10 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 xl:hidden"
                    aria-label="Abrir menú"
                >
                    <i
                        data-lucide="menu"
                        class="h-6 w-6"
                    ></i>
                </button>

                <span
                    class="text-[25px] font-normal text-[#6c6c6c] dark:text-slate-400"
                >
                    Gintly
                </span>

                <i
                    data-lucide="chevron-right"
                    class="h-[21px] w-[21px] text-[#777777] dark:text-slate-400"
                ></i>

                <strong
                    class="truncate text-[25px] font-semibold text-[#161616] dark:text-white"
                >
                    Inventario y bodega
                </strong>
            </div>

            <div
                class="flex items-center gap-[40px]"
            >
                <!-- Notificaciones -->
                <button
                    type="button"
                    class="relative flex h-[60px] w-[60px] items-center justify-center rounded-full bg-[#f4f4f4] text-[#686868] transition hover:bg-[#ececec] dark:bg-slate-800 dark:text-slate-300"
                    aria-label="Notificaciones"
                >
                    <i
                        data-lucide="bell"
                        class="h-[20px] w-[20px]"
                    ></i>

                    <span
                        class="absolute right-[4px] top-[-1px] flex h-[20px] min-w-[20px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
                    >
                        2
                    </span>
                </button>

                <!-- Perfil -->
                <button
                    type="button"
                    class="flex items-center gap-[42px]"
                    aria-label="Perfil de usuario"
                >
                    <img
                        src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=128&h=128&q=85"
                        alt="Perfil de usuario"
                        class="h-[81px] w-[81px] rounded-full object-cover"
                    >

                    <i
                        data-lucide="chevrons-up-down"
                        class="h-[22px] w-[22px] text-[#696969] dark:text-slate-300"
                    ></i>
                </button>
            </div>
        </header>

        <!-- =====================================================
             MAIN
        ====================================================== -->
        <main
            class="px-[44px] pb-[70px] pt-[55px] xl:px-[44px]"
        >
            <!-- Título -->
            <section>
                <h1
                    class="text-[45px] font-bold leading-[1.1] tracking-[-0.025em] text-black dark:text-white"
                >
                    Catálogo y datos maestros
                </h1>

                <p
                    class="mt-[18px] text-[20px] text-[#727272] dark:text-slate-400"
                >
                    Gestión Centralizada de productos, precios y calificaciones
                </p>
            </section>

            <!-- =================================================
                 BUSCADOR Y FILTROS
            ================================================== -->
            <section
                class="mt-[57px] rounded-[21px] border border-[#c8c8c8] bg-white px-[34px] py-[34px] shadow-card dark:border-slate-700 dark:bg-slate-900"
            >
                <div
                    class="flex flex-col gap-[28px] 2xl:flex-row 2xl:items-center"
                >
                    <!-- Búsqueda -->
                    <label
                        class="relative block min-w-0 flex-1"
                    >
                        <span class="sr-only">
                            Buscar producto
                        </span>

                        <i
                            data-lucide="search"
                            class="pointer-events-none absolute left-[20px] top-1/2 h-[19px] w-[19px] -translate-y-1/2 text-[#7c7c7c]"
                        ></i>

                        <input
                            id="product-search"
                            type="search"
                            autocomplete="off"
                            placeholder="Busca el producto"
                            class="h-[54px] w-full rounded-[14px] border border-[#bebebe] bg-white pl-[58px] pr-[20px] text-[17px] text-[#353535] outline-none transition placeholder:text-[#727272] focus:border-gintly-primary focus:ring-2 focus:ring-gintly-primary/15 dark:border-slate-600 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500"
                        >
                    </label>

                    <!-- Categorías -->
                    <div
                        id="category-filters"
                        class="gintly-scrollbar flex max-w-full items-center gap-[20px] overflow-x-auto pb-1 2xl:pb-0"
                        aria-label="Filtros por categoría"
                    >
                        <button
                            type="button"
                            data-category-id=""
                            aria-pressed="true"
                            class="category-chip h-[54px] shrink-0 rounded-[15px] border border-gintly-primary bg-gintly-primary px-[25px] text-[16px] font-medium text-white"
                        >
                            Todas
                        </button>
                    </div>
                </div>
            </section>

<!-- =================================================
                 TABLA DE PRODUCTOS
            ================================================== -->
            <section
                class="mt-[54px] overflow-hidden rounded-[21px] border border-[#c8c8c8] bg-white shadow-card dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Acciones -->
                <div
                    class="flex min-h-[119px] flex-col gap-5 px-[31px] py-[25px] lg:flex-row lg:items-center lg:justify-end"
                >
                    <div class="mr-auto lg:mr-[8px]">
                        <strong
                            id="product-count"
                            class="text-[26px] font-semibold text-[#333333] dark:text-white"
                        >
                            0 productos
                        </strong>
                    </div>

                    <button
                        id="add-category-btn"
                        type="button"
                        class="inline-flex h-[55px] items-center justify-center gap-[15px] rounded-[14px] bg-[#f3f3f3] px-[20px] text-[16px] font-medium text-[#656565] transition hover:bg-[#e9e9e9] disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        <i
                            data-lucide="plus"
                            class="h-[21px] w-[21px]"
                        ></i>

                        <span>
                            Agregar categoría de producto
                        </span>
                    </button>

                    <button
                        id="export-btn"
                        type="button"
                        class="inline-flex h-[55px] items-center justify-center gap-[15px] rounded-[14px] border border-gintly-primary bg-white px-[21px] text-[16px] font-semibold text-[#174457] transition hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-900 dark:hover:bg-slate-800"
                    >
                        <i
                            data-lucide="download"
                            class="h-[20px] w-[20px]"
                        ></i>

                        <span>Exportar para Excel</span>
                    </button>

                    <button
                        id="add-product-btn"
                        type="button"
                        class="inline-flex h-[55px] items-center justify-center gap-[15px] rounded-[14px] bg-gintly-primary px-[22px] text-[16px] font-semibold text-white transition hover:bg-gintly-primaryDark disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <i
                            data-lucide="plus"
                            class="h-[21px] w-[21px]"
                        ></i>

                        <span>Agregar nuevo producto</span>
                    </button>
                </div>

                <!-- Tabla -->
                <div
                    class="gintly-scrollbar overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[1420px] border-collapse"
                    >
                        <thead>
                        <tr
                            class="h-[86px] border-y border-[#d4d4d4] bg-[#f1f1f1] text-left dark:border-slate-700 dark:bg-slate-800"
                        >
                            <th
                                class="w-[120px] px-[32px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                SKU
                            </th>

                            <th
                                class="w-[175px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Producto
                            </th>

                            <th
                                class="w-[145px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Categoría
                            </th>

                            <th
                                class="w-[130px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Marca
                            </th>

                            <th
                                class="w-[90px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Unidad
                            </th>

                            <th
                                class="w-[160px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Precio de venta
                            </th>

                            <th
                                class="w-[120px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Costo
                            </th>

                            <th
                                class="w-[110px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Tipo
                            </th>

                            <th
                                class="w-[130px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Impuestos
                            </th>

                            <th
                                class="w-[110px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Estados
                            </th>

                            <th
                                class="w-[105px] px-[18px] text-[16px] font-normal text-[#696969] dark:text-slate-300"
                            >
                                Edición
                            </th>
                        </tr>
                        </thead>

                        <tbody
                            id="products-table-body"
                            aria-live="polite"
                        ></tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div
                    id="pagination-container"
                    class="hidden min-h-[76px] items-center justify-between border-t border-[#dddddd] px-[32px] dark:border-slate-700"
                >
                    <span
                        id="pagination-label"
                        class="text-sm text-slate-500 dark:text-slate-400"
                    ></span>

                    <div class="flex gap-2">
                        <button
                            id="prev-page-btn"
                            type="button"
                            class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-300 px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            <i
                                data-lucide="chevron-left"
                                class="h-4 w-4"
                            ></i>

                            Anterior
                        </button>

                        <button
                            id="next-page-btn"
                            type="button"
                            class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-300 px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Siguiente

                            <i
                                data-lucide="chevron-right"
                                class="h-4 w-4"
                            ></i>
                        </button>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<!-- =============================================================
     MODAL · REGISTRO DE PRODUCTOS
============================================================= -->
<div
    id="product-modal"
    class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black bg-opacity-50 p-4 opacity-0 transition-opacity duration-200"
    role="dialog"
    aria-modal="true"
    aria-labelledby="product-modal-title"
>
    <div
        class="flex min-h-full items-center justify-center py-5"
    >
        <div
            id="product-modal-panel"
            class="relative w-full max-w-[1025px] translate-y-3 scale-[0.985] rounded-[28px] bg-white px-[46px] pb-[46px] pt-[48px] shadow-modal transition duration-200 dark:bg-slate-900"
        >
            <!-- Cabecera -->
            <div
                class="mb-[48px] pr-[80px]"
            >
                <p
                    class="text-[20px] font-normal text-[#3f3f3f] dark:text-slate-300"
                >
                    Nuevo producto
                </p>

                <h2
                    id="product-modal-title"
                    class="mt-[13px] text-[28px] font-bold leading-none text-black dark:text-white"
                >
                    Registro de productos
                </h2>
            </div>

            <!-- X -->
            <button
                id="close-product-modal-btn"
                type="button"
                class="absolute right-[48px] top-[52px] flex h-[62px] w-[62px] items-center justify-center rounded-full bg-[#f4f4f4] text-[#6d6d6d] transition hover:bg-[#e9e9e9] dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                aria-label="Cerrar registro de producto"
            >
                <i
                    data-lucide="x"
                    class="h-[28px] w-[28px]"
                ></i>
            </button>

            <form
                id="product-form"
                novalidate
            >
                <!-- Error general -->
                <div
                    id="product-form-global-error"
                    class="mb-7 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                    role="alert"
                ></div>

                <div
                    class="grid grid-cols-1 gap-x-[35px] gap-y-[47px] md:grid-cols-2"
                >

                    <!-- =========================================
                         SKU
                    ========================================== -->
                    <div>
                        <label
                            for="product-sku"
                            class="mb-[15px] block text-[20px] font-normal text-[#3f3f3f] dark:text-slate-200"
                        >
                            SKU / código
                        </label>

                        <div class="relative">
                            <input
                                id="product-sku"
                                name="sku"
                                type="text"
                                maxlength="60"
                                autocomplete="off"
                                placeholder="Ejemplo: ARR-001"
                                class="h-[58px] w-full rounded-[6px] border border-[#bdbdbd] bg-white px-[17px] pr-[48px] text-[17px] text-[#353535] outline-none transition placeholder:text-[#777777] focus:border-gintly-primary focus:ring-2 focus:ring-gintly-primary/10 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                            >

                            <i
                                data-lucide="badge-x"
                                class="pointer-events-none absolute right-[15px] top-1/2 h-[22px] w-[22px] -translate-y-1/2 text-[#747474]"
                            ></i>
                        </div>

                        <div
                            data-validation-for="sku"
                            class="mt-[15px] flex min-h-[47px] items-center gap-[11px] rounded-[5px] bg-[#f2f2f2] px-[12px] text-[17px] text-[#6c6c6c] transition dark:bg-slate-800 dark:text-slate-400"
                        >
                            <i
                                data-lucide="info"
                                class="h-[22px] w-[22px] shrink-0"
                            ></i>

                            <span>
                                Es de carácter obligatorio
                            </span>
                        </div>
                    </div>

                    <!-- =========================================
                         NOMBRE
                    ========================================== -->
                    <div>
                        <label
                            for="product-name"
                            class="mb-[15px] block text-[20px] font-normal text-[#3f3f3f] dark:text-slate-200"
                        >
                            Nombre del producto
                        </label>

                        <div class="relative">
                            <input
                                id="product-name"
                                name="name"
                                type="text"
                                maxlength="160"
                                autocomplete="off"
                                placeholder="Ejemplo: Arroz Faisán"
                                class="h-[58px] w-full rounded-[6px] border border-[#bdbdbd] bg-white px-[17px] pr-[48px] text-[17px] text-[#353535] outline-none transition placeholder:text-[#777777] focus:border-gintly-primary focus:ring-2 focus:ring-gintly-primary/10 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                            >

                            <i
                                data-lucide="badge-x"
                                class="pointer-events-none absolute right-[15px] top-1/2 h-[22px] w-[22px] -translate-y-1/2 text-[#747474]"
                            ></i>
                        </div>

                        <div
                            data-validation-for="name"
                            class="mt-[15px] flex min-h-[47px] items-center gap-[11px] rounded-[5px] bg-[#f2f2f2] px-[12px] text-[17px] text-[#6c6c6c] transition dark:bg-slate-800 dark:text-slate-400"
                        >
                            <i
                                data-lucide="info"
                                class="h-[22px] w-[22px] shrink-0"
                            ></i>

                            <span>
                                Es de carácter obligatorio
                            </span>
                        </div>
                    </div>

                    <!-- =========================================
                         MARCA
                    ========================================== -->
                    <div>
                        <label
                            for="product-brand"
                            class="mb-[15px] block text-[20px] font-normal text-[#3f3f3f] dark:text-slate-200"
                        >
                            Marca
                        </label>

                        <div class="relative">
                            <input
                                id="product-brand"
                                name="brand_name"
                                type="text"
                                maxlength="120"
                                autocomplete="off"
                                list="product-brand-options"
                                placeholder="Ejemplo: Faisán"
                                class="h-[58px] w-full rounded-[6px] border border-[#bdbdbd] bg-white px-[17px] pr-[48px] text-[17px] text-[#353535] outline-none transition placeholder:text-[#777777] focus:border-gintly-primary focus:ring-2 focus:ring-gintly-primary/10 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                            >

                            <i
                                data-lucide="badge-x"
                                class="pointer-events-none absolute right-[15px] top-1/2 h-[22px] w-[22px] -translate-y-1/2 text-[#747474]"
                            ></i>
                        </div>

                        <datalist
                            id="product-brand-options"
                        ></datalist>

                        <div
                            data-validation-for="brand_id"
                            class="mt-[15px] flex min-h-[47px] items-center gap-[11px] rounded-[5px] bg-[#f2f2f2] px-[12px] text-[17px] text-[#6c6c6c] transition dark:bg-slate-800 dark:text-slate-400"
                        >
                            <i
                                data-lucide="info"
                                class="h-[22px] w-[22px] shrink-0"
                            ></i>

                            <span>
                                Es de carácter obligatorio
                            </span>
                        </div>
                    </div>

                    <!-- =========================================
                         CATEGORÍA
                    ========================================== -->
                    <div class="relative">
                        <label
                            class="mb-[15px] block text-[20px] font-normal text-[#3f3f3f] dark:text-slate-200"
                        >
                            Categorías
                        </label>

                        <input
                            id="product-category-id"
                            name="category_id"
                            type="hidden"
                        >

                        <button
                            id="product-category-trigger"
                            type="button"
                            aria-expanded="false"
                            class="flex h-[58px] w-full items-center justify-between rounded-[6px] border border-[#bdbdbd] bg-white px-[16px] text-left text-[17px] text-[#696969] outline-none transition focus:border-gintly-primary focus:ring-2 focus:ring-gintly-primary/10 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300"
                        >
                            <span
                                class="flex min-w-0 items-center gap-[16px]"
                            >
                                <i
                                    data-lucide="archive"
                                    class="h-[23px] w-[23px] shrink-0"
                                ></i>

                                <span
                                    id="product-category-label"
                                    class="truncate"
                                >
                                    Seleccione una categoría
                                </span>
                            </span>

                            <i
                                data-lucide="chevron-down"
                                class="h-[21px] w-[21px] shrink-0"
                            ></i>
                        </button>

                        <div
                            id="product-category-menu"
                            class="gintly-scrollbar absolute left-0 right-0 top-[99px] z-[120] hidden max-h-[340px] overflow-y-auto rounded-[7px] border border-[#bdbdbd] bg-white shadow-xl dark:border-slate-600 dark:bg-slate-900"
                        ></div>

                        <div
                            data-validation-for="category_id"
                            class="mt-[15px] flex min-h-[47px] items-center gap-[11px] rounded-[5px] bg-[#f2f2f2] px-[12px] text-[17px] text-[#6c6c6c] transition dark:bg-slate-800 dark:text-slate-400"
                        >
                            <i
                                data-lucide="info"
                                class="h-[22px] w-[22px] shrink-0"
                            ></i>

                            <span>
                                Es de carácter obligatorio
                            </span>
                        </div>
                    </div>

                    <!-- =========================================
                         UNIDAD
                    ========================================== -->
                    <div class="relative">
                        <label
                            class="mb-[15px] block text-[20px] font-normal text-[#3f3f3f] dark:text-slate-200"
                        >
                            Unidad de medida
                        </label>

                        <input
                            id="product-unit-id"
                            name="unit_id"
                            type="hidden"
                        >

                        <button
                            id="product-unit-trigger"
                            type="button"
                            aria-expanded="false"
                            class="flex h-[58px] w-full items-center justify-between rounded-[6px] border border-[#bdbdbd] bg-white px-[16px] text-left text-[17px] text-[#777777] outline-none transition focus:border-gintly-primary focus:ring-2 focus:ring-gintly-primary/10 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300"
                        >
                            <span
                                id="product-unit-label"
                            >
                                Ejemplo: Litro
                            </span>

                            <i
                                data-lucide="chevron-down"
                                class="h-[21px] w-[21px]"
                            ></i>
                        </button>

                        <div
                            id="product-unit-menu"
                            class="gintly-scrollbar absolute left-0 right-0 top-[99px] z-[120] hidden max-h-[340px] overflow-y-auto rounded-[7px] border border-[#bdbdbd] bg-white shadow-xl dark:border-slate-600 dark:bg-slate-900"
                        ></div>

                        <div
                            data-validation-for="unit_id"
                            class="mt-[15px] flex min-h-[47px] items-center gap-[11px] rounded-[5px] bg-[#f2f2f2] px-[12px] text-[17px] text-[#6c6c6c] transition dark:bg-slate-800 dark:text-slate-400"
                        >
                            <i
                                data-lucide="info"
                                class="h-[22px] w-[22px] shrink-0"
                            ></i>

                            <span>
                                Es de carácter obligatorio
                            </span>
                        </div>
                    </div>

                    <!-- =========================================
                         TIPO
                    ========================================== -->
                    <div>
                        <span
                            class="mb-[15px] block text-[20px] font-normal text-[#3f3f3f] dark:text-slate-200"
                        >
                            Tipo de productos
                        </span>

                        <div
                            id="product-type-selector"
                            class="grid grid-cols-3 gap-[18px]"
                        >
                            <button
                                type="button"
                                data-product-type="simple"
                                class="h-[58px] rounded-[17px] bg-gintly-primary px-4 text-[17px] font-semibold text-white transition"
                            >
                                Simple
                            </button>

                            <button
                                type="button"
                                data-product-type="compound"
                                class="h-[58px] rounded-[17px] bg-[#f1f1f1] px-4 text-[17px] font-medium text-[#696969] transition hover:bg-[#e8e8e8] dark:bg-slate-800 dark:text-slate-300"
                            >
                                Compuesto
                            </button>

                            <button
                                type="button"
                                data-product-type="service"
                                class="h-[58px] rounded-[17px] bg-[#f1f1f1] px-4 text-[17px] font-medium text-[#696969] transition hover:bg-[#e8e8e8] dark:bg-slate-800 dark:text-slate-300"
                            >
                                Servicio
                            </button>
                        </div>

                        <div
                            data-validation-for="type"
                            class="mt-[15px] flex min-h-[47px] items-center gap-[11px] rounded-[5px] bg-[#f2f2f2] px-[12px] text-[17px] text-[#6c6c6c] transition dark:bg-slate-800 dark:text-slate-400"
                        >
                            <i
                                data-lucide="info"
                                class="h-[22px] w-[22px] shrink-0"
                            ></i>

                            <span>
                                Es de carácter obligatorio
                            </span>
                        </div>
                    </div>

                    <!-- =========================================
                         IMPUESTO
                    ========================================== -->
                    <div>
                        <span
                            class="mb-[15px] block text-[20px] font-normal text-[#3f3f3f] dark:text-slate-200"
                        >
                            Impuesto
                        </span>

                        <div
                            id="product-tax-selector"
                            class="grid grid-cols-2 gap-[20px]"
                        >
                            <button
                                type="button"
                                data-product-taxable="false"
                                class="h-[58px] rounded-[17px] bg-gintly-primary px-4 text-[17px] font-semibold text-white transition"
                            >
                                Exento
                            </button>

                            <button
                                type="button"
                                data-product-taxable="true"
                                class="h-[58px] rounded-[17px] bg-[#f1f1f1] px-4 text-[17px] font-medium text-[#696969] transition hover:bg-[#e8e8e8] dark:bg-slate-800 dark:text-slate-300"
                            >
                                15% IVA
                            </button>
                        </div>

                        <div
                            data-validation-for="is_taxable"
                            class="mt-[15px] flex min-h-[47px] items-center gap-[11px] rounded-[5px] bg-[#f2f2f2] px-[12px] text-[17px] text-[#6c6c6c] transition dark:bg-slate-800 dark:text-slate-400"
                        >
                            <i
                                data-lucide="info"
                                class="h-[22px] w-[22px] shrink-0"
                            ></i>

                            <span>
                                Es de carácter obligatorio
                            </span>
                        </div>
                    </div>

                    <!-- =========================================
                         PRECIO
                    ========================================== -->
                    <div>
                        <label
                            for="product-sale-price"
                            class="mb-[15px] block text-[20px] font-normal text-[#3f3f3f] dark:text-slate-200"
                        >
                            Precio de venta
                        </label>

                        <div class="relative">
                            <span
                                class="pointer-events-none absolute left-[17px] top-1/2 -translate-y-1/2 text-[17px] text-[#737373]"
                            >
                                C$
                            </span>

                            <input
                                id="product-sale-price"
                                name="sale_price"
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                placeholder="0.00"
                                class="h-[58px] w-full rounded-[6px] border border-[#bdbdbd] bg-white pl-[45px] pr-[48px] text-[17px] text-[#353535] outline-none transition placeholder:text-[#777777] focus:border-gintly-primary focus:ring-2 focus:ring-gintly-primary/10 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                            >

                            <i
                                data-lucide="badge-x"
                                class="pointer-events-none absolute right-[15px] top-1/2 h-[22px] w-[22px] -translate-y-1/2 text-[#747474]"
                            ></i>
                        </div>

                        <div
                            data-validation-for="sale_price"
                            class="mt-[15px] flex min-h-[47px] items-center gap-[11px] rounded-[5px] bg-[#f2f2f2] px-[12px] text-[17px] text-[#6c6c6c] transition dark:bg-slate-800 dark:text-slate-400"
                        >
                            <i
                                data-lucide="info"
                                class="h-[22px] w-[22px] shrink-0"
                            ></i>

                            <span>
                                Es de carácter obligatorio
                            </span>
                        </div>
                    </div>
                </div>

                <!--
                    El Figma no contiene costo.
                    El backend permite cost = 0.00.
                -->
                <input
                    id="product-cost"
                    name="cost"
                    type="hidden"
                    value="0.00"
                >

                <!-- Submit -->
                <div
                    class="mt-[42px] flex justify-end"
                >
                    <button
                        id="product-submit-btn"
                        type="submit"
                        class="inline-flex h-[58px] min-w-[210px] items-center justify-center gap-3 rounded-[14px] bg-gintly-primary px-7 text-[17px] font-semibold text-white transition hover:bg-gintly-primaryDark disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <i
                            data-lucide="plus"
                            class="h-[21px] w-[21px]"
                        ></i>

                        <span>
                            Registrar producto
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =============================================================
     CONFIGURACIÓN FRONTEND
============================================================= -->
<script>
    /*
     * app.debug = true:
     * utiliza MockData para trabajar sin backend activo.
     *
     * app.debug = false:
     * consume directamente /api/v1.
     */
    window.CONFIG_DEV = @json(config('app.debug'));

    /*
     * Como la vista Blade se sirve desde Laravel, la URL base
     * puede resolverse automáticamente.
     */
    window.GINTLY_API_BASE_URL = @json(url(''));
</script>

<!-- JS del módulo -->
<script
    src="{{ asset('js/catalogo.js') }}"
    defer
></script>

</body>
</html>
