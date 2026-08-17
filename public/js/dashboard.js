'use strict';

/**
 * ================================================================
 * GINTLY · DASHBOARD
 * ================================================================
 *
 * Frontend:
 * - Vanilla JS
 * - fetch / async / await
 * - SVG nativo para gráficos
 * - sin Chart.js, React, Vue ni bundlers
 *
 * Producción:
 * GET /api/v1/dashboard
 *
 * NOTA DE ARQUITECTURA:
 * La ruta agregadora todavía no forma parte de los contratos HTTP
 * entregados. Este frontend formaliza su contrato esperado.
 *
 * El backend deberá devolver:
 *
 * {
 *   "data": {
 *      "meta": {...},
 *      "kpis": {...},
 *      "weekly_sales": {...},
 *      "cash_status": [...],
 *      "inventory_comparison": {...},
 *      "receivables": {...},
 *      "alerts": [...]
 *   }
 * }
 *
 * business_id nunca viaja desde el navegador.
 */

(() => {

    /* ============================================================
     * CONFIG
     * ============================================================ */

    const CONFIG_DEV =
        Boolean(
            window.CONFIG_DEV
        );

    const API_BASE_URL =
        String(
            window.GINTLY_API_BASE_URL ?? ''
        ).replace(/\/+$/, '');

    const DASHBOARD_API_URL =
        String(
            window.GINTLY_DASHBOARD_API_URL ??
            `${API_BASE_URL}/api/v1/dashboard`
        );

    const API = Object.freeze({
        dashboard:
            DASHBOARD_API_URL,

        logout:
            `${API_BASE_URL}/api/v1/logout`
    });

    /* ============================================================
     * STATE
     * ============================================================ */

    const state = {
        data: null,

        loading: false,

        currency: 'NIO',

        search: '',

        hiddenSalesSeries: new Set(),

        errorTimer: null
    };

    /* ============================================================
     * DOM
     * ============================================================ */

    const dom = {};

    /* ============================================================
     * MOCK DATA
     * ============================================================ */

    const MOCK_DATA = {
        meta: {
            generated_at:
                '2026-08-17T14:41:00-06:00',

            branches_label:
                'Todas las sucursales',

            exchange_rate:
                '36.8000'
        },

        kpis: {
            sales_day: {
                amount:
                    '110400.00',

                transactions:
                    300,

                branches:
                    5,

                cash_registers:
                    7,

                trend_percent:
                    '8.3',

                comparison:
                    'vs. ayer'
            },

            average_ticket: {
                amount:
                    '368.00',

                transactions:
                    300,

                trend_percent:
                    '2.1',

                comparison:
                    'vs. semana'
            },

            overdue_60: {
                amount:
                    '45000.00',

                portfolio_total:
                    '180000.00'
            },

            inventory_mismatch: {
                amount:
                    '-21.00',

                sku_count:
                    3,

                branches:
                    2
            },

            active_alerts: {
                count:
                    2,

                in_review:
                    2,

                resolved:
                    1
            },

            gross_margin: {
                amount:
                    '110400.00',

                trend_percent:
                    '8.3'
            },

            returns: {
                amount:
                    '1250.00',

                tickets:
                    3
            },

            purchase_orders_approval: {
                count:
                    3,

                exposed_amount:
                    '6500.00'
            }
        },

        weekly_sales: {
            labels: [
                'Lun',
                'Mar',
                'Mié',
                'Jue',
                'Vie',
                'Sáb',
                'Dom'
            ],

            physical: [
                '4800.00',
                '7200.00',
                '6100.00',
                '8400.00',
                '7900.00',
                '7600.00',
                '8900.00'
            ],

            marketplace: [
                '2300.00',
                '3300.00',
                '2600.00',
                '3100.00',
                '3500.00',
                '3000.00',
                '3300.00'
            ],

            wholesale: [
                '3100.00',
                '4200.00',
                '3600.00',
                '4500.00',
                '5000.00',
                '4600.00',
                '4900.00'
            ],

            target: [
                '10500.00',
                '11000.00',
                '10800.00',
                '11500.00',
                '12000.00',
                '11800.00',
                '12500.00'
            ]
        },

        cash_status: [
            {
                id:
                    1,

                code:
                    '#01',

                user:
                    'Ana Ramírez',

                branch:
                    'Centro',

                opened_at:
                    '08:00',

                amount:
                    '47820.00',

                status:
                    'abierta',

                status_label:
                    'ABIERTA'
            },

            {
                id:
                    2,

                code:
                    '#02',

                user:
                    'Pedro Sánchez',

                branch:
                    'Centro',

                opened_at:
                    '08:00',

                amount:
                    '47820.00',

                status:
                    'abierta',

                status_label:
                    'ABIERTA'
            },

            {
                id:
                    3,

                code:
                    '#03',

                user:
                    'María López',

                branch:
                    'Centro',

                opened_at:
                    '14:00',

                amount:
                    '38950.00',

                status:
                    'bloqueada',

                status_label:
                    'BLOQUEADA'
            },

            {
                id:
                    4,

                code:
                    '#04',

                user:
                    'Sofía Vega',

                branch:
                    'Sur',

                opened_at:
                    '08:00',

                amount:
                    '38950.00',

                status:
                    'cerrada',

                status_label:
                    'CERRADA'
            }
        ],

        inventory_comparison: {
            branches: [
                {
                    name:
                        'Centro',

                    logical:
                        '92.00',

                    physical:
                        '89.00',

                    mismatch:
                        '96.00'
                },

                {
                    name:
                        'Norte',

                    logical:
                        '87.00',

                    physical:
                        '84.00',

                    mismatch:
                        '95.00'
                },

                {
                    name:
                        'Sur',

                    logical:
                        '75.00',

                    physical:
                        '76.00',

                    mismatch:
                        '102.00'
                },

                {
                    name:
                        'Plaza',

                    logical:
                        '74.00',

                    physical:
                        '77.00',

                    mismatch:
                        '114.00'
                },

                {
                    name:
                        'Oriente',

                    logical:
                        '79.00',

                    physical:
                        '81.00',

                    mismatch:
                        '119.00'
                }
            ]
        },

        receivables: {
            total:
                '45000.00',

            months: [
                {
                    label:
                        'Mar',

                    current:
                        '26000.00',

                    days_1_30:
                        '8500.00',

                    days_31_60:
                        '4500.00',

                    days_60_plus:
                        '3000.00'
                },

                {
                    label:
                        'Abr',

                    current:
                        '27000.00',

                    days_1_30:
                        '9200.00',

                    days_31_60:
                        '4700.00',

                    days_60_plus:
                        '3400.00'
                },

                {
                    label:
                        'May',

                    current:
                        '24500.00',

                    days_1_30:
                        '11000.00',

                    days_31_60:
                        '5000.00',

                    days_60_plus:
                        '4000.00'
                },

                {
                    label:
                        'Jun',

                    current:
                        '27000.00',

                    days_1_30:
                        '9800.00',

                    days_31_60:
                        '4800.00',

                    days_60_plus:
                        '3800.00'
                },

                {
                    label:
                        'Jul',

                    current:
                        '27500.00',

                    days_1_30:
                        '10500.00',

                    days_31_60:
                        '5200.00',

                    days_60_plus:
                        '4500.00'
                }
            ]
        },

        alerts: [
            {
                id:
                    91,

                code:
                    'ALT-0091',

                title:
                    'Cierre bloqueado - Caja #03',

                severity:
                    'critica',

                severity_label:
                    'CIERRE BLOQUEADO',

                status:
                    'detectada',

                detail:
                    'Faltante de C$ 2,450.00. Cajero: María López. Sucursal Centro, 22:47 hrs.',

                relative_time:
                    'hace 6 min'
            },

            {
                id:
                    92,

                code:
                    'ALT-0092',

                title:
                    'Cierre bloqueado - Caja #03',

                severity:
                    'critica',

                severity_label:
                    'CIERRE BLOQUEADO',

                status:
                    'en_revision',

                detail:
                    'Faltante de C$ 2,450.00. Cajero: María López. Sucursal Centro, 22:47 hrs.',

                relative_time:
                    'hace 8 min'
            },

            {
                id:
                    93,

                code:
                    'ALT-0093',

                title:
                    'Cierre bloqueado - Caja #03',

                severity:
                    'critica',

                severity_label:
                    'CIERRE BLOQUEADO',

                status:
                    'en_revision',

                detail:
                    'Faltante de C$ 2,450.00. Cajero: María López. Sucursal Centro, 22:47 hrs.',

                relative_time:
                    'hace 9 min'
            },

            {
                id:
                    94,

                code:
                    'ALT-0094',

                title:
                    'Cierre bloqueado - Caja #03',

                severity:
                    'advertencia',

                severity_label:
                    'ALERTA IMPORTANTE',

                status:
                    'detectada',

                detail:
                    'Diferencia pendiente de conciliación. Sucursal Centro.',

                relative_time:
                    'hace 12 min'
            },

            {
                id:
                    95,

                code:
                    'ALT-0095',

                title:
                    'Descuadre de inventario',

                severity:
                    'informativa',

                severity_label:
                    'EN REVISIÓN',

                status:
                    'resuelta',

                detail:
                    'Diferencia asociada a conteo físico. Bodega Central.',

                relative_time:
                    'hace 18 min'
            }
        ]
    };

    /* ============================================================
     * INIT
     * ============================================================ */

    document.addEventListener(
        'DOMContentLoaded',
        initialize
    );

    async function initialize() {

        cacheDom();

        bindEvents();

        restoreTheme();

        refreshIcons();

        await loadDashboard();
    }

    /* ============================================================
     * DOM CACHE
     * ============================================================ */

    function cacheDom() {

        /* Sidebar */

        dom.sidebar =
            document.getElementById(
                'app-sidebar'
            );

        dom.sidebarOverlay =
            document.getElementById(
                'sidebar-overlay'
            );

        dom.mobileSidebarBtn =
            document.getElementById(
                'mobile-sidebar-btn'
            );

        dom.sidebarCollapseBtn =
            document.getElementById(
                'sidebar-collapse-btn'
            );

        dom.darkModeToggle =
            document.getElementById(
                'dark-mode-toggle'
            );

        dom.darkModeKnob =
            document.getElementById(
                'dark-mode-knob'
            );

        dom.logoutBtn =
            document.getElementById(
                'logout-btn'
            );

        /* Header */

        dom.globalSearch =
            document.getElementById(
                'global-search'
            );

        dom.updatedAt =
            document.getElementById(
                'dashboard-updated-at'
            );

        dom.exportBtn =
            document.getElementById(
                'export-dashboard-btn'
            );

        dom.refreshBtn =
            document.getElementById(
                'refresh-dashboard-btn'
            );

        /* Main KPIs */

        dom.salesValue =
            document.getElementById(
                'kpi-sales-value'
            );

        dom.salesDetail =
            document.getElementById(
                'kpi-sales-detail'
            );

        dom.salesTrend =
            document.getElementById(
                'kpi-sales-trend'
            );

        dom.ticketValue =
            document.getElementById(
                'kpi-ticket-value'
            );

        dom.ticketDetail =
            document.getElementById(
                'kpi-ticket-detail'
            );

        dom.ticketTrend =
            document.getElementById(
                'kpi-ticket-trend'
            );

        dom.overdueValue =
            document.getElementById(
                'kpi-overdue-value'
            );

        dom.overdueDetail =
            document.getElementById(
                'kpi-overdue-detail'
            );

        dom.inventoryMismatchValue =
            document.getElementById(
                'kpi-inventory-mismatch-value'
            );

        dom.inventoryMismatchDetail =
            document.getElementById(
                'kpi-inventory-mismatch-detail'
            );

        dom.activeAlertsValue =
            document.getElementById(
                'kpi-active-alerts-value'
            );

        dom.activeAlertsDetail =
            document.getElementById(
                'kpi-active-alerts-detail'
            );

        /* Charts */

        dom.salesCurrencySelect =
            document.getElementById(
                'sales-currency-select'
            );

        dom.salesChartLegend =
            document.getElementById(
                'sales-chart-legend'
            );

        dom.salesChart =
            document.getElementById(
                'sales-chart-svg'
            );

        dom.inventoryChart =
            document.getElementById(
                'inventory-chart-svg'
            );

        dom.receivablesChart =
            document.getElementById(
                'receivables-chart-svg'
            );

        dom.receivablesTotal =
            document.getElementById(
                'receivables-total-value'
            );

        /* Cash */

        dom.cashStatusList =
            document.getElementById(
                'cash-status-list'
            );

        /* Mini KPIs */

        dom.grossMargin =
            document.getElementById(
                'mini-gross-margin'
            );

        dom.grossMarginTrend =
            document.getElementById(
                'mini-gross-margin-trend'
            );

        dom.returns =
            document.getElementById(
                'mini-returns'
            );

        dom.returnsDetail =
            document.getElementById(
                'mini-returns-detail'
            );

        dom.purchaseOrders =
            document.getElementById(
                'mini-purchase-orders'
            );

        dom.purchaseOrdersDetail =
            document.getElementById(
                'mini-purchase-orders-detail'
            );

        /* Alerts */

        dom.alertsList =
            document.getElementById(
                'alerts-list'
            );

        dom.alertsHeaderCount =
            document.getElementById(
                'alerts-header-count'
            );

        dom.alertsStatusSummary =
            document.getElementById(
                'alerts-status-summary'
            );

        /* Error */

        dom.errorToast =
            document.getElementById(
                'dashboard-error-toast'
            );

        dom.errorTitle =
            document.getElementById(
                'dashboard-error-title'
            );

        dom.errorMessage =
            document.getElementById(
                'dashboard-error-message'
            );

        dom.closeErrorToastBtn =
            document.getElementById(
                'close-error-toast-btn'
            );
    }

    /* ============================================================
     * EVENTS
     * ============================================================ */

    function bindEvents() {

        dom.refreshBtn.addEventListener(
            'click',
            loadDashboard
        );

        dom.exportBtn.addEventListener(
            'click',
            exportDashboard
        );

        dom.globalSearch.addEventListener(
            'input',
            debounce(
                (event) => {

                    state.search =
                        normalizeText(
                            event.target.value
                        );

                    renderCashStatus();

                    renderAlerts();

                },
                150
            )
        );

        dom.salesCurrencySelect.addEventListener(
            'change',
            (event) => {

                state.currency =
                    event.target.value;

                renderDashboard();
            }
        );

        dom.salesChartLegend.addEventListener(
            'click',
            (event) => {

                const button =
                    event.target.closest(
                        '[data-sales-series]'
                    );

                if (!button) {
                    return;
                }

                const series =
                    button.dataset.salesSeries;

                if (
                    state.hiddenSalesSeries.has(
                        series
                    )
                ) {

                    state.hiddenSalesSeries.delete(
                        series
                    );

                } else {

                    state.hiddenSalesSeries.add(
                        series
                    );
                }

                button.dataset.disabled =
                    state.hiddenSalesSeries.has(
                        series
                    )
                        ? 'true'
                        : 'false';

                renderSalesChart();
            }
        );

        dom.closeErrorToastBtn.addEventListener(
            'click',
            hideError
        );

        dom.darkModeToggle.addEventListener(
            'click',
            toggleTheme
        );

        dom.mobileSidebarBtn.addEventListener(
            'click',
            openSidebar
        );

        dom.sidebarCollapseBtn.addEventListener(
            'click',
            closeSidebar
        );

        dom.sidebarOverlay.addEventListener(
            'click',
            closeSidebar
        );

        dom.logoutBtn.addEventListener(
            'click',
            logout
        );

        window.addEventListener(
            'resize',
            () => {

                if (
                    window.innerWidth >= 1280
                ) {
                    closeSidebar();
                }
            }
        );
    }

    /* ============================================================
     * LOAD DASHBOARD
     * ============================================================ */

    async function loadDashboard() {

        if (
            state.loading
        ) {
            return;
        }

        state.loading =
            true;

        hideError();

        setButtonBusy(
            dom.refreshBtn,
            true,
            'Actualizando'
        );

        try {

            let data;

            if (CONFIG_DEV) {

                await delay(
                    350
                );

                data =
                    deepClone(
                        MOCK_DATA
                    );

                data.meta.generated_at =
                    new Date().toISOString();

            } else {

                const response =
                    await apiFetch(
                        API.dashboard
                    );

                data =
                    response?.data ??
                    response;

                validateDashboardPayload(
                    data
                );
            }

            state.data =
                data;

            renderDashboard();

        } catch (error) {

            console.error(
                'Error cargando dashboard:',
                error
            );

            showHttpError(
                error
            );

        } finally {

            state.loading =
                false;

            setButtonBusy(
                dom.refreshBtn,
                false
            );
        }
    }

    function validateDashboardPayload(
        data
    ) {

        if (
            !data ||
            typeof data !== 'object'
        ) {

            const error =
                new Error(
                    'El servidor devolvió un contrato de Dashboard inválido.'
                );

            error.status =
                500;

            throw error;
        }

        const required =
            [
                'meta',
                'kpis',
                'weekly_sales',
                'cash_status',
                'inventory_comparison',
                'receivables',
                'alerts'
            ];

        for (
            const key
            of required
        ) {

            if (
                !(key in data)
            ) {

                const error =
                    new Error(
                        `El contrato del Dashboard no contiene "${key}".`
                    );

                error.status =
                    500;

                throw error;
            }
        }
    }

    /* ============================================================
     * MAIN RENDER
     * ============================================================ */

    function renderDashboard() {

        if (
            !state.data
        ) {
            return;
        }

        renderUpdateDate();

        renderKpis();

        renderSalesChart();

        renderCashStatus();

        renderInventoryChart();

        renderReceivablesChart();

        renderMiniKpis();

        renderAlerts();

        refreshIcons();
    }

    /* ============================================================
     * DATE
     * ============================================================ */

    function renderUpdateDate() {

        const generatedAt =
            state.data?.meta?.generated_at;

        const date =
            generatedAt
                ? new Date(
                    generatedAt
                )
                : new Date();

        const formatted =
            new Intl.DateTimeFormat(
                'es-NI',
                {
                    weekday:
                        'long',

                    day:
                        'numeric',

                    month:
                        'long',

                    year:
                        'numeric',

                    hour:
                        '2-digit',

                    minute:
                        '2-digit',

                    hour12:
                        false
                }
            ).format(
                date
            );

        const branches =
            state.data?.meta?.branches_label ??
            'Todas las sucursales';

        dom.updatedAt.textContent =
            `${capitalize(formatted)} · ${branches}`;
    }

    /* ============================================================
     * KPIs
     * ============================================================ */

    function renderKpis() {

        const kpis =
            state.data.kpis;

        const sales =
            kpis.sales_day;

        dom.salesValue.textContent =
            formatMoney(
                sales.amount
            );

        dom.salesDetail.textContent =
            `${sales.branches} sucursales · ${sales.cash_registers} caja`;

        dom.salesTrend.textContent =
            trendText(
                sales.trend_percent,
                sales.comparison
            );

        const ticket =
            kpis.average_ticket;

        dom.ticketValue.textContent =
            formatMoney(
                ticket.amount
            );

        dom.ticketDetail.textContent =
            `${ticket.transactions} transacciones`;

        dom.ticketTrend.textContent =
            trendText(
                ticket.trend_percent,
                ticket.comparison
            );

        const overdue =
            kpis.overdue_60;

        dom.overdueValue.textContent =
            formatMoney(
                overdue.amount
            );

        dom.overdueDetail.textContent =
            `${formatMoney(overdue.portfolio_total)} cartera total`;

        const inventoryMismatch =
            kpis.inventory_mismatch;

        dom.inventoryMismatchValue.textContent =
            `${formatSignedAmount(inventoryMismatch.amount)} C$`;

        dom.inventoryMismatchDetail.textContent =
            `${inventoryMismatch.sku_count} SKUs · ${inventoryMismatch.branches} sucursales`;

        const activeAlerts =
            kpis.active_alerts;

        dom.activeAlertsValue.textContent =
            String(
                activeAlerts.count
            );

        dom.activeAlertsDetail.textContent =
            `${activeAlerts.in_review} en revisión · ${activeAlerts.resolved} resueltas`;
    }

    function renderMiniKpis() {

        const kpis =
            state.data.kpis;

        dom.grossMargin.textContent =
            formatMoney(
                kpis.gross_margin.amount
            );

        dom.grossMarginTrend.textContent =
            trendText(
                kpis.gross_margin.trend_percent,
                'vs. ayer'
            );

        dom.returns.textContent =
            formatMoney(
                kpis.returns.amount
            );

        dom.returnsDetail.textContent =
            `${kpis.returns.tickets} tickets hoy`;

        dom.purchaseOrders.textContent =
            `${kpis.purchase_orders_approval.count} OC`;

        dom.purchaseOrdersDetail.textContent =
            `${formatMoney(kpis.purchase_orders_approval.exposed_amount)} expuestos`;
    }
/* ============================================================
     * CASH STATUS
     * ============================================================ */

    function renderCashStatus() {

        dom.cashStatusList.replaceChildren();

        if (
            !state.data
        ) {
            return;
        }

        const rows =
            state.data.cash_status.filter(
                (item) => {

                    if (
                        state.search === ''
                    ) {
                        return true;
                    }

                    return normalizeText(
                        [
                            item.user,
                            item.branch,
                            item.code,
                            item.status_label
                        ].join(' ')
                    ).includes(
                        state.search
                    );
                }
            );

        if (
            rows.length === 0
        ) {

            const empty =
                document.createElement(
                    'div'
                );

            empty.className =
                'py-10 text-center text-[11px] text-[#888888]';

            empty.textContent =
                'No hay cajas que coincidan con la búsqueda.';

            dom.cashStatusList.appendChild(
                empty
            );

            return;
        }

        const fragment =
            document.createDocumentFragment();

        for (
            const item
            of rows
        ) {

            const row =
                document.createElement(
                    'article'
                );

            row.className =
                'grid grid-cols-[44px_minmax(0,1fr)_auto] items-center gap-[12px] border-b border-[#e2e2e2] py-[20px] last:border-b-0 dark:border-slate-700';

            const code =
                document.createElement(
                    'div'
                );

            code.className =
                'flex h-[35px] w-[35px] items-center justify-center rounded-[7px] text-[9px] font-bold ' +
                cashCodeColor(
                    item.status
                );

            code.textContent =
                item.code;

            const information =
                document.createElement(
                    'div'
                );

            information.className =
                'min-w-0';

            const name =
                document.createElement(
                    'strong'
                );

            name.className =
                'block truncate text-[11px] font-semibold text-[#333333] dark:text-slate-100';

            name.textContent =
                item.user;

            const detail =
                document.createElement(
                    'span'
                );

            detail.className =
                'mt-[3px] block text-[9px] text-[#878787]';

            detail.textContent =
                `${item.branch} · Apertura ${item.opened_at}`;

            information.append(
                name,
                detail
            );

            const right =
                document.createElement(
                    'div'
                );

            right.className =
                'text-right';

            const amount =
                document.createElement(
                    'strong'
                );

            amount.className =
                'block text-[11px] font-bold';

            amount.textContent =
                formatMoney(
                    item.amount
                );

            const badge =
                document.createElement(
                    'span'
                );

            badge.className =
                `mt-[5px] inline-flex rounded-full px-[7px] py-[3px] text-[8px] font-bold ${cashBadgeClass(item.status)}`;

            badge.textContent =
                item.status_label;

            right.append(
                amount,
                badge
            );

            row.append(
                code,
                information,
                right
            );

            fragment.appendChild(
                row
            );
        }

        dom.cashStatusList.appendChild(
            fragment
        );
    }

    function cashBadgeClass(
        status
    ) {

        switch (status) {

            case 'abierta':
                return 'cash-badge-open';

            case 'bloqueada':
            case 'descuadrada':
                return 'cash-badge-blocked';

            case 'sobrante':
                return 'cash-badge-surplus';

            case 'cerrada':
            default:
                return 'cash-badge-closed';
        }
    }

    function cashCodeColor(
        status
    ) {

        switch (status) {

            case 'abierta':
                return 'bg-[#d9f5e4] text-[#159854]';

            case 'bloqueada':
            case 'descuadrada':
                return 'bg-[#fbd9db] text-[#db2c32]';

            default:
                return 'bg-[#e2e6ff] text-[#536dff]';
        }
    }

    /* ============================================================
     * ALERTS
     * ============================================================ */

    function renderAlerts() {

        dom.alertsList.replaceChildren();

        if (
            !state.data
        ) {
            return;
        }

        const allAlerts =
            state.data.alerts;

        const alerts =
            allAlerts.filter(
                (alert) => {

                    if (
                        state.search === ''
                    ) {
                        return true;
                    }

                    return normalizeText(
                        [
                            alert.title,
                            alert.detail,
                            alert.code,
                            alert.status,
                            alert.severity_label
                        ].join(' ')
                    ).includes(
                        state.search
                    );
                }
            );

        dom.alertsHeaderCount.textContent =
            String(
                allAlerts.length
            );

        renderAlertsSummary(
            allAlerts
        );

        if (
            alerts.length === 0
        ) {

            const empty =
                document.createElement(
                    'div'
                );

            empty.className =
                'py-12 text-center text-[11px] text-[#888888]';

            empty.textContent =
                'No hay anomalías que coincidan con la búsqueda.';

            dom.alertsList.appendChild(
                empty
            );

            return;
        }

        const fragment =
            document.createDocumentFragment();

        for (
            const alert
            of alerts
        ) {

            const row =
                document.createElement(
                    'article'
                );

            row.className =
                'grid grid-cols-[48px_minmax(0,1fr)_auto] items-center gap-[13px] border-b border-[#e5e5e5] py-[17px] last:border-b-0 dark:border-slate-700';

            const iconBox =
                document.createElement(
                    'div'
                );

            iconBox.className =
                'flex h-[42px] w-[42px] items-center justify-center rounded-[8px] bg-[#fbd9db] text-[#dc3036]';

            const icon =
                document.createElement(
                    'i'
                );

            icon.dataset.lucide =
                'triangle-alert';

            icon.className =
                'h-[21px] w-[21px]';

            iconBox.appendChild(
                icon
            );

            const content =
                document.createElement(
                    'div'
                );

            content.className =
                'min-w-0';

            const titleRow =
                document.createElement(
                    'div'
                );

            titleRow.className =
                'flex flex-wrap items-center gap-[8px]';

            const title =
                document.createElement(
                    'strong'
                );

            title.className =
                'text-[11px] font-semibold text-[#333333] dark:text-slate-100';

            title.textContent =
                alert.title;

            const badge =
                document.createElement(
                    'span'
                );

            badge.className =
                `rounded-[4px] px-[6px] py-[3px] text-[8px] font-semibold ${alertSeverityClass(alert.severity)}`;

            badge.textContent =
                alert.severity_label;

            titleRow.append(
                title,
                badge
            );

            const detail =
                document.createElement(
                    'p'
                );

            detail.className =
                'mt-[5px] truncate text-[9px] text-[#7f7f7f]';

            detail.textContent =
                alert.detail;

            content.append(
                titleRow,
                detail
            );

            const actions =
                document.createElement(
                    'button'
                );

            actions.type =
                'button';

            actions.dataset.alertId =
                String(
                    alert.id
                );

            actions.className =
                'flex items-center gap-[10px] text-[9px] text-[#777777] transition hover:text-gintly-primary';

            const time =
                document.createElement(
                    'span'
                );

            time.textContent =
                alert.relative_time;

            const code =
                document.createElement(
                    'span'
                );

            code.textContent =
                alert.code;

            const arrow =
                document.createElement(
                    'i'
                );

            arrow.dataset.lucide =
                'chevron-right';

            arrow.className =
                'h-[13px] w-[13px]';

            actions.append(
                time,
                code,
                arrow
            );

            actions.addEventListener(
                'click',
                () => {

                    console.log(
                        'Abrir detalle de anomalía',
                        alert.id
                    );
                }
            );

            row.append(
                iconBox,
                content,
                actions
            );

            fragment.appendChild(
                row
            );
        }

        dom.alertsList.appendChild(
            fragment
        );

        refreshIcons();
    }

    function renderAlertsSummary(
        alerts
    ) {

        const pending =
            alerts.filter(
                (item) =>
                    item.status ===
                    'detectada'
            ).length;

        const review =
            alerts.filter(
                (item) =>
                    item.status ===
                    'en_revision'
            ).length;

        const resolved =
            alerts.filter(
                (item) =>
                    item.status ===
                    'resuelta'
            ).length;

        dom.alertsStatusSummary.innerHTML =
            `<span class="text-[#888888]">${pending} pendientes</span>` +
            `<span class="mx-2 text-[#bbbbbb]">·</span>` +
            `<span class="text-[#e78621]">${review} revisión</span>` +
            `<span class="mx-2 text-[#bbbbbb]">·</span>` +
            `<span class="text-[#19a15d]">${resolved} resueltas</span>`;
    }

    function alertSeverityClass(
        severity
    ) {

        switch (severity) {

            case 'critica':
                return 'alert-severity-critical';

            case 'advertencia':
                return 'alert-severity-warning';

            default:
                return 'alert-severity-info';
        }
    }

    /* ============================================================
     * SALES CHART
     * ============================================================ */

    function renderSalesChart() {

        if (
            !state.data
        ) {
            return;
        }

        const svg =
            dom.salesChart;

        svg.replaceChildren();

        const source =
            state.data.weekly_sales;

        const width =
            780;

        const height =
            360;

        const margin = {
            top:
                20,

            right:
                18,

            bottom:
                38,

            left:
                62
        };

        const plotWidth =
            width -
            margin.left -
            margin.right;

        const plotHeight =
            height -
            margin.top -
            margin.bottom;

        const physical =
            seriesToNumbers(
                source.physical,
                'physical'
            );

        const marketplace =
            seriesToNumbers(
                source.marketplace,
                'marketplace'
            );

        const wholesale =
            seriesToNumbers(
                source.wholesale,
                'wholesale'
            );

        const target =
            source.target.map(
                chartMoneyValue
            );

        const cumulative =
            physical.map(
                (value, index) =>
                    value +
                    marketplace[index] +
                    wholesale[index]
            );

        const max =
            Math.max(
                ...cumulative,
                ...target,
                1
            ) * 1.12;

        drawGrid(
            svg,
            {
                width,
                height,
                margin,
                plotWidth,
                plotHeight,
                max,
                labels:
                    source.labels,
                money:
                    true
            }
        );

        const zero =
            physical.map(
                () => 0
            );

        const physicalTop =
            physical;

        const marketplaceTop =
            physical.map(
                (value, index) =>
                    value +
                    marketplace[index]
            );

        const wholesaleTop =
            marketplaceTop.map(
                (value, index) =>
                    value +
                    wholesale[index]
            );

        drawAreaLayer(
            svg,
            zero,
            physicalTop,
            '#ffbd82',
            '#ff8c2a',
            {
                margin,
                plotWidth,
                plotHeight,
                max
            }
        );

        drawAreaLayer(
            svg,
            physicalTop,
            marketplaceTop,
            '#9eacff',
            '#506dff',
            {
                margin,
                plotWidth,
                plotHeight,
                max
            }
        );

        drawAreaLayer(
            svg,
            marketplaceTop,
            wholesaleTop,
            '#8cd3a5',
            '#25aa5d',
            {
                margin,
                plotWidth,
                plotHeight,
                max
            }
        );

        drawLine(
            svg,
            target,
            '#4cad69',
            {
                margin,
                plotWidth,
                plotHeight,
                max,
                dashed:
                    true
            }
        );
    }

    function seriesToNumbers(
        source,
        seriesName
    ) {

        if (
            state.hiddenSalesSeries.has(
                seriesName
            )
        ) {

            return source.map(
                () => 0
            );
        }

        return source.map(
            chartMoneyValue
        );
    }

    function drawAreaLayer(
        svg,
        lower,
        upper,
        fill,
        stroke,
        options
    ) {

        const {
            margin,
            plotWidth,
            plotHeight,
            max
        } = options;

        const upperPoints =
            upper.map(
                (value, index) => {

                    return [
                        chartX(
                            index,
                            upper.length,
                            margin.left,
                            plotWidth
                        ),

                        chartY(
                            value,
                            max,
                            margin.top,
                            plotHeight
                        )
                    ];
                }
            );

        const lowerPoints =
            lower.map(
                (value, index) => {

                    return [
                        chartX(
                            index,
                            lower.length,
                            margin.left,
                            plotWidth
                        ),

                        chartY(
                            value,
                            max,
                            margin.top,
                            plotHeight
                        )
                    ];
                }
            ).reverse();

        const polygon =
            createSvgElement(
                'polygon'
            );

        polygon.setAttribute(
            'points',
            [
                ...upperPoints,
                ...lowerPoints
            ]
                .map(
                    (point) =>
                        point.join(',')
                )
                .join(' ')
        );

        polygon.setAttribute(
            'fill',
            fill
        );

        polygon.setAttribute(
            'fill-opacity',
            '.72'
        );

        svg.appendChild(
            polygon
        );

        const path =
            createSvgElement(
                'path'
            );

        path.setAttribute(
            'd',
            pointsToSmoothPath(
                upperPoints
            )
        );

        path.setAttribute(
            'fill',
            'none'
        );

        path.setAttribute(
            'stroke',
            stroke
        );

        path.setAttribute(
            'stroke-width',
            '2'
        );

        svg.appendChild(
            path
        );
    }

    function drawLine(
        svg,
        values,
        stroke,
        options
    ) {

        const points =
            values.map(
                (value, index) => [
                    chartX(
                        index,
                        values.length,
                        options.margin.left,
                        options.plotWidth
                    ),

                    chartY(
                        value,
                        options.max,
                        options.margin.top,
                        options.plotHeight
                    )
                ]
            );

        const path =
            createSvgElement(
                'path'
            );

        path.setAttribute(
            'd',
            pointsToSmoothPath(
                points
            )
        );

        path.setAttribute(
            'fill',
            'none'
        );

        path.setAttribute(
            'stroke',
            stroke
        );

        path.setAttribute(
            'stroke-width',
            '2'
        );

        if (
            options.dashed
        ) {

            path.setAttribute(
                'stroke-dasharray',
                '7 7'
            );
        }

        svg.appendChild(
            path
        );
    }

    /* ============================================================
     * INVENTORY CHART
     * ============================================================ */

    function renderInventoryChart() {

        if (
            !state.data
        ) {
            return;
        }

        const svg =
            dom.inventoryChart;

        svg.replaceChildren();

        const rows =
            state.data
                .inventory_comparison
                .branches;

        const width =
            360;

        const maxValue =
            Math.max(
                ...rows.flatMap(
                    (item) => [
                        Number(item.logical),
                        Number(item.physical),
                        Number(item.mismatch)
                    ]
                ),
                1
            );

        const startX =
            76;

        const maxWidth =
            245;

        const groupHeight =
            58;

        rows.forEach(
            (item, index) => {

                const baseY =
                    28 +
                    index *
                    groupHeight;

                appendSvgText(
                    svg,
                    item.name,
                    3,
                    baseY + 14,
                    {
                        size:
                            9,

                        fill:
                            '#777777'
                    }
                );

                drawHorizontalBar(
                    svg,
                    startX,
                    baseY,
                    scaleValue(
                        Number(item.logical),
                        maxValue,
                        maxWidth
                    ),
                    10,
                    '#506dff'
                );

                drawHorizontalBar(
                    svg,
                    startX,
                    baseY + 14,
                    scaleValue(
                        Number(item.physical),
                        maxValue,
                        maxWidth
                    ),
                    10,
                    '#1aad58'
                );

                drawHorizontalBar(
                    svg,
                    startX,
                    baseY + 28,
                    scaleValue(
                        Number(item.mismatch),
                        maxValue,
                        maxWidth
                    ),
                    10,
                    '#dd1e24'
                );
            }
        );
    }

    function drawHorizontalBar(
        svg,
        x,
        y,
        width,
        height,
        fill
    ) {

        const rect =
            createSvgElement(
                'rect'
            );

        rect.setAttribute(
            'x',
            String(x)
        );

        rect.setAttribute(
            'y',
            String(y)
        );

        rect.setAttribute(
            'width',
            String(width)
        );

        rect.setAttribute(
            'height',
            String(height)
        );

        rect.setAttribute(
            'rx',
            '2'
        );

        rect.setAttribute(
            'fill',
            fill
        );

        svg.appendChild(
            rect
        );
    }

    /* ============================================================
     * RECEIVABLES CHART
     * ============================================================ */

    function renderReceivablesChart() {

        if (
            !state.data
        ) {
            return;
        }

        const data =
            state.data.receivables;

        dom.receivablesTotal.textContent =
            formatMoney(
                data.total
            );

        const svg =
            dom.receivablesChart;

        svg.replaceChildren();

        const width =
            560;

        const height =
            300;

        const margin = {
            top:
                15,

            right:
                18,

            bottom:
                35,

            left:
                58
        };

        const plotWidth =
            width -
            margin.left -
            margin.right;

        const plotHeight =
            height -
            margin.top -
            margin.bottom;

        const totals =
            data.months.map(
                (month) =>
                    Number(month.current) +
                    Number(month.days_1_30) +
                    Number(month.days_31_60) +
                    Number(month.days_60_plus)
            );

        const max =
            Math.max(
                ...totals,
                1
            ) * 1.1;

        drawGrid(
            svg,
            {
                width,
                height,
                margin,
                plotWidth,
                plotHeight,
                max,
                labels:
                    data.months.map(
                        (month) =>
                            month.label
                    ),

                money:
                    true,

                verticalLines:
                    false
            }
        );

        const barWidth =
            22;

        data.months.forEach(
            (month, index) => {

                const values = [
                    {
                        value:
                            Number(
                                month.current
                            ),

                        color:
                            '#17a95d'
                    },

                    {
                        value:
                            Number(
                                month.days_1_30
                            ),

                        color:
                            '#506dff'
                    },

                    {
                        value:
                            Number(
                                month.days_31_60
                            ),

                        color:
                            '#ff8a25'
                    },

                    {
                        value:
                            Number(
                                month.days_60_plus
                            ),

                        color:
                            '#dc1f26'
                    }
                ];

                const x =
                    chartX(
                        index,
                        data.months.length,
                        margin.left,
                        plotWidth
                    ) -
                    barWidth / 2;

                let currentY =
                    margin.top +
                    plotHeight;

                for (
                    const segment
                    of values
                ) {

                    const segmentHeight =
                        (
                            segment.value /
                            max
                        ) *
                        plotHeight;

                    currentY -=
                        segmentHeight;

                    const rect =
                        createSvgElement(
                            'rect'
                        );

                    rect.setAttribute(
                        'x',
                        String(x)
                    );

                    rect.setAttribute(
                        'y',
                        String(currentY)
                    );

                    rect.setAttribute(
                        'width',
                        String(barWidth)
                    );

                    rect.setAttribute(
                        'height',
                        String(
                            Math.max(
                                0,
                                segmentHeight
                            )
                        )
                    );

                    rect.setAttribute(
                        'fill',
                        segment.color
                    );

                    svg.appendChild(
                        rect
                    );
                }
            }
        );
    }

    /* ============================================================
     * GENERIC GRID
     * ============================================================ */

    function drawGrid(
        svg,
        options
    ) {

        const horizontalLines =
            5;

        for (
            let i = 0;
            i <= horizontalLines;
            i++
        ) {

            const y =
                options.margin.top +
                (
                    options.plotHeight /
                    horizontalLines
                ) *
                i;

            const line =
                createSvgElement(
                    'line'
                );

            line.setAttribute(
                'x1',
                String(
                    options.margin.left
                )
            );

            line.setAttribute(
                'x2',
                String(
                    options.margin.left +
                    options.plotWidth
                )
            );

            line.setAttribute(
                'y1',
                String(y)
            );

            line.setAttribute(
                'y2',
                String(y)
            );

            line.setAttribute(
                'stroke',
                '#c9c9c9'
            );

            line.setAttribute(
                'stroke-width',
                '1'
            );

            line.setAttribute(
                'stroke-dasharray',
                '6 7'
            );

            svg.appendChild(
                line
            );

            const value =
                options.max -
                (
                    options.max /
                    horizontalLines
                ) *
                i;

            appendSvgText(
                svg,
                options.money
                    ? compactMoney(
                        value
                    )
                    : Math.round(
                        value
                    ).toString(),

                3,
                y + 3,
                {
                    size:
                        9,

                    fill:
                        '#8a8a8a'
                }
            );
        }

        options.labels.forEach(
            (label, index) => {

                const x =
                    chartX(
                        index,
                        options.labels.length,
                        options.margin.left,
                        options.plotWidth
                    );

                if (
                    options.verticalLines !== false
                ) {

                    const line =
                        createSvgElement(
                            'line'
                        );

                    line.setAttribute(
                        'x1',
                        String(x)
                    );

                    line.setAttribute(
                        'x2',
                        String(x)
                    );

                    line.setAttribute(
                        'y1',
                        String(
                            options.margin.top
                        )
                    );

                    line.setAttribute(
                        'y2',
                        String(
                            options.margin.top +
                            options.plotHeight
                        )
                    );

                    line.setAttribute(
                        'stroke',
                        '#d0d0d0'
                    );

                    line.setAttribute(
                        'stroke-dasharray',
                        '6 7'
                    );

                    svg.appendChild(
                        line
                    );
                }

                appendSvgText(
                    svg,
                    label,
                    x,
                    options.margin.top +
                    options.plotHeight +
                    24,
                    {
                        size:
                            9,

                        fill:
                            '#777777',

                        anchor:
                            'middle'
                    }
                );
            }
        );
    }
/* ============================================================
     * SVG HELPERS
     * ============================================================ */

    function chartX(
        index,
        length,
        start,
        width
    ) {

        if (
            length <= 1
        ) {
            return (
                start +
                width / 2
            );
        }

        return (
            start +
            (
                width /
                (
                    length - 1
                )
            ) *
            index
        );
    }

    function chartY(
        value,
        max,
        start,
        height
    ) {

        return (
            start +
            height -
            (
                value /
                max
            ) *
            height
        );
    }

    function pointsToSmoothPath(
        points
    ) {

        if (
            points.length === 0
        ) {
            return '';
        }

        if (
            points.length === 1
        ) {

            return (
                `M ${points[0][0]} ${points[0][1]}`
            );
        }

        let path =
            `M ${points[0][0]} ${points[0][1]}`;

        for (
            let i = 1;
            i < points.length;
            i++
        ) {

            const previous =
                points[
                    i - 1
                ];

            const current =
                points[i];

            const middleX =
                (
                    previous[0] +
                    current[0]
                ) / 2;

            path +=
                ` C ${middleX} ${previous[1]}, ` +
                `${middleX} ${current[1]}, ` +
                `${current[0]} ${current[1]}`;
        }

        return path;
    }

    function createSvgElement(
        name
    ) {

        return document.createElementNS(
            'http://www.w3.org/2000/svg',
            name
        );
    }

    function appendSvgText(
        svg,
        value,
        x,
        y,
        options = {}
    ) {

        const text =
            createSvgElement(
                'text'
            );

        text.setAttribute(
            'x',
            String(x)
        );

        text.setAttribute(
            'y',
            String(y)
        );

        text.setAttribute(
            'font-size',
            String(
                options.size ??
                10
            )
        );

        text.setAttribute(
            'fill',
            options.fill ??
            '#777777'
        );

        text.setAttribute(
            'text-anchor',
            options.anchor ??
            'start'
        );

        text.textContent =
            value;

        svg.appendChild(
            text
        );
    }

    function scaleValue(
        value,
        max,
        width
    ) {

        if (
            max <= 0
        ) {
            return 0;
        }

        return (
            value /
            max
        ) *
        width;
    }

    /* ============================================================
     * CURRENCY
     * ============================================================ */

    function formatMoney(
        value
    ) {

        const amount =
            currencyAmount(
                value
            );

        const formatter =
            new Intl.NumberFormat(
                'es-NI',
                {
                    minimumFractionDigits:
                        2,

                    maximumFractionDigits:
                        2
                }
            );

        return (
            `${currencySymbol()} ${formatter.format(amount)}`
        );
    }

    function chartMoneyValue(
        value
    ) {

        return currencyAmount(
            value
        );
    }

    function currencyAmount(
        value
    ) {

        const amount =
            Number(
                value ?? 0
            );

        if (
            state.currency === 'USD'
        ) {

            const rate =
                Number(
                    state.data?.meta?.exchange_rate ??
                    36.8
                );

            return rate > 0
                ? amount / rate
                : amount;
        }

        return amount;
    }

    function currencySymbol() {

        return state.currency === 'USD'
            ? '$'
            : 'C$';
    }

    function compactMoney(
        value
    ) {

        const prefix =
            currencySymbol();

        if (
            value >= 1000000
        ) {

            return (
                `${prefix} ${(value / 1000000).toFixed(1)}M`
            );
        }

        if (
            value >= 1000
        ) {

            return (
                `${prefix} ${(value / 1000).toFixed(0)},000`
            );
        }

        return (
            `${prefix} ${Math.round(value)}`
        );
    }

    function formatSignedAmount(
        value
    ) {

        const amount =
            Number(
                value ?? 0
            );

        return new Intl.NumberFormat(
            'es-NI',
            {
                minimumFractionDigits:
                    0,

                maximumFractionDigits:
                    2
            }
        ).format(
            amount
        );
    }

    function trendText(
        percent,
        comparison
    ) {

        const numeric =
            Number(
                percent ?? 0
            );

        const arrow =
            numeric >= 0
                ? '↑'
                : '↓';

        return (
            `${arrow} ${Math.abs(numeric)}% ${comparison ?? ''}`
        );
    }

    /* ============================================================
     * EXPORT
     * ============================================================ */

    function exportDashboard() {

        if (
            !state.data
        ) {
            return;
        }

        setButtonBusy(
            dom.exportBtn,
            true,
            'Exportando'
        );

        try {

            const kpis =
                state.data.kpis;

            const rows = [
                [
                    'Métrica',
                    'Valor'
                ],

                [
                    'Ventas del día',
                    kpis.sales_day.amount
                ],

                [
                    'Ticket promedio',
                    kpis.average_ticket.amount
                ],

                [
                    'CxC vencida +60 días',
                    kpis.overdue_60.amount
                ],

                [
                    'Descuadre inventario',
                    kpis.inventory_mismatch.amount
                ],

                [
                    'Alertas activas',
                    kpis.active_alerts.count
                ],

                [
                    'Margen bruto',
                    kpis.gross_margin.amount
                ],

                [
                    'Devoluciones',
                    kpis.returns.amount
                ],

                [
                    'OC en aprobación',
                    kpis.purchase_orders_approval.count
                ]
            ];

            const csv =
                rows
                    .map(
                        (row) =>
                            row
                                .map(
                                    csvEscape
                                )
                                .join(',')
                    )
                    .join('\r\n');

            const blob =
                new Blob(
                    [
                        `\uFEFF${csv}`
                    ],
                    {
                        type:
                            'text/csv;charset=utf-8;'
                    }
                );

            const url =
                URL.createObjectURL(
                    blob
                );

            const anchor =
                document.createElement(
                    'a'
                );

            anchor.href =
                url;

            anchor.download =
                `gintly-dashboard-${localDateString()}.csv`;

            document.body.appendChild(
                anchor
            );

            anchor.click();

            anchor.remove();

            URL.revokeObjectURL(
                url
            );

        } finally {

            setButtonBusy(
                dom.exportBtn,
                false
            );
        }
    }

    function csvEscape(
        value
    ) {

        const text =
            String(
                value ?? ''
            );

        if (
            text.includes(',') ||
            text.includes('"') ||
            text.includes('\n')
        ) {

            return (
                `"${text.replaceAll('"', '""')}"`
            );
        }

        return text;
    }

    /* ============================================================
     * API
     * ============================================================ */

    async function apiFetch(
        url,
        options = {}
    ) {

        const headers =
            new Headers(
                options.headers ??
                {}
            );

        headers.set(
            'Accept',
            'application/json'
        );

        headers.set(
            'X-Requested-With',
            'XMLHttpRequest'
        );

        const token =
            getAccessToken();

        if (
            token
        ) {

            headers.set(
                'Authorization',
                `Bearer ${token}`
            );
        }

        const xsrfToken =
            getCookie(
                'XSRF-TOKEN'
            );

        if (
            xsrfToken &&
            !headers.has(
                'X-XSRF-TOKEN'
            )
        ) {

            headers.set(
                'X-XSRF-TOKEN',
                decodeURIComponent(
                    xsrfToken
                )
            );
        }

        let response;

        try {

            response =
                await fetch(
                    url,
                    {
                        ...options,

                        headers,

                        credentials:
                            'include'
                    }
                );

        } catch (networkError) {

            const error =
                new Error(
                    'No fue posible conectar con el servidor.'
                );

            error.status =
                0;

            error.cause =
                networkError;

            throw error;
        }

        const payload =
            await parseJsonResponse(
                response
            );

        if (
            !response.ok
        ) {

            const error =
                new Error(
                    resolveHttpErrorMessage(
                        response.status,
                        payload
                    )
                );

            error.status =
                response.status;

            error.payload =
                payload;

            throw error;
        }

        return payload;
    }

    async function parseJsonResponse(
        response
    ) {

        if (
            response.status ===
            204
        ) {
            return null;
        }

        const type =
            response.headers.get(
                'content-type'
            ) ?? '';

        if (
            !type.includes(
                'application/json'
            )
        ) {
            return null;
        }

        try {

            return await response.json();

        } catch {

            return null;
        }
    }

    function resolveHttpErrorMessage(
        status,
        payload
    ) {

        if (
            payload &&
            typeof payload.message ===
                'string' &&
            payload.message.trim() !== ''
        ) {

            return payload.message;
        }

        switch (status) {

            case 401:
                return (
                    'La sesión ha expirado o no es válida.'
                );

            case 403:
                return (
                    'No tienes permisos para consultar el Dashboard.'
                );

            case 404:
                return (
                    'El endpoint agregador del Dashboard todavía no está disponible en la API.'
                );

            case 422:
                return (
                    'La solicitud del Dashboard contiene parámetros no válidos.'
                );

            case 429:
                return (
                    'Se realizaron demasiadas solicitudes. Intenta nuevamente en unos segundos.'
                );

            case 500:
                return (
                    'El servidor no pudo calcular las métricas del Dashboard.'
                );

            default:
                return (
                    `No fue posible sincronizar el Dashboard (HTTP ${status}).`
                );
        }
    }

    /* ============================================================
     * ERROR TOAST
     * ============================================================ */

    function showHttpError(
        error
    ) {

        let title =
            'Error de sincronización';

        if (
            error?.status === 403
        ) {

            title =
                'Acceso denegado';

        } else if (
            error?.status === 500
        ) {

            title =
                'Error interno del servidor';

        } else if (
            error?.status === 404
        ) {

            title =
                'Endpoint no disponible';
        }

        dom.errorTitle.textContent =
            title;

        dom.errorMessage.textContent =
            error?.message ||
            'No fue posible actualizar el Dashboard.';

        dom.errorToast.classList.remove(
            'hidden'
        );

        refreshIcons();

        if (
            state.errorTimer
        ) {

            window.clearTimeout(
                state.errorTimer
            );
        }

        state.errorTimer =
            window.setTimeout(
                hideError,
                7000
            );
    }

    function hideError() {

        dom.errorToast.classList.add(
            'hidden'
        );

        if (
            state.errorTimer
        ) {

            window.clearTimeout(
                state.errorTimer
            );

            state.errorTimer =
                null;
        }
    }

    /* ============================================================
     * AUTH
     * ============================================================ */

    function getAccessToken() {

        return (
            localStorage.getItem(
                'gintly_access_token'
            ) ||

            sessionStorage.getItem(
                'gintly_access_token'
            ) ||

            localStorage.getItem(
                'auth_token'
            ) ||

            sessionStorage.getItem(
                'auth_token'
            ) ||

            ''
        );
    }

    function getCookie(
        name
    ) {

        const prefix =
            `${name}=`;

        for (
            const rawCookie
            of document.cookie.split(';')
        ) {

            const cookie =
                rawCookie.trim();

            if (
                cookie.startsWith(
                    prefix
                )
            ) {

                return cookie.substring(
                    prefix.length
                );
            }
        }

        return '';
    }

    async function logout() {

        setButtonBusy(
            dom.logoutBtn,
            true,
            'Cerrando sesión'
        );

        try {

            if (
                !CONFIG_DEV
            ) {

                await apiFetch(
                    API.logout,
                    {
                        method:
                            'POST'
                    }
                );
            }

        } catch (error) {

            console.error(
                'No fue posible cerrar sesión:',
                error
            );

        } finally {

            clearStoredTokens();

            setButtonBusy(
                dom.logoutBtn,
                false
            );

            console.log(
                'Sesión cerrada'
            );
        }
    }

    function clearStoredTokens() {

        const keys = [
            'gintly_access_token',
            'auth_token'
        ];

        for (
            const key
            of keys
        ) {

            localStorage.removeItem(
                key
            );

            sessionStorage.removeItem(
                key
            );
        }
    }

    /* ============================================================
     * DARK MODE
     * ============================================================ */

    function restoreTheme() {

        applyTheme(
            localStorage.getItem(
                'gintly_theme'
            ) === 'dark'
        );
    }

    function toggleTheme() {

        const nextDark =
            !document.documentElement
                .classList
                .contains(
                    'dark'
                );

        applyTheme(
            nextDark
        );

        localStorage.setItem(
            'gintly_theme',
            nextDark
                ? 'dark'
                : 'light'
        );
    }

    function applyTheme(
        dark
    ) {

        document.documentElement
            .classList
            .toggle(
                'dark',
                dark
            );

        dom.darkModeToggle.setAttribute(
            'aria-checked',
            dark
                ? 'true'
                : 'false'
        );

        dom.darkModeToggle.classList.toggle(
            'bg-gintly-primary',
            dark
        );

        dom.darkModeKnob.style.transform =
            dark
                ? 'translateX(17px)'
                : 'translateX(0)';
    }

    /* ============================================================
     * SIDEBAR
     * ============================================================ */

    function openSidebar() {

        dom.sidebar.dataset.open =
            'true';

        dom.sidebarOverlay.classList.remove(
            'hidden'
        );

        document.body.classList.add(
            'overflow-hidden'
        );
    }

    function closeSidebar() {

        dom.sidebar.dataset.open =
            'false';

        dom.sidebarOverlay.classList.add(
            'hidden'
        );

        document.body.classList.remove(
            'overflow-hidden'
        );
    }

    /* ============================================================
     * GENERAL UTILS
     * ============================================================ */

    function chartMoneyValue(
        value
    ) {

        return currencyAmount(
            value
        );
    }

    function normalizeText(
        value
    ) {

        return String(
            value ?? ''
        )
            .normalize(
                'NFD'
            )
            .replace(
                /[\u0300-\u036f]/g,
                ''
            )
            .toLowerCase()
            .trim();
    }

    function debounce(
        callback,
        milliseconds
    ) {

        let timer =
            null;

        return (...args) => {

            window.clearTimeout(
                timer
            );

            timer =
                window.setTimeout(
                    () => {

                        callback(
                            ...args
                        );

                    },
                    milliseconds
                );
        };
    }

    function delay(
        milliseconds
    ) {

        return new Promise(
            (resolve) => {

                window.setTimeout(
                    resolve,
                    milliseconds
                );
            }
        );
    }

    function deepClone(
        value
    ) {

        return JSON.parse(
            JSON.stringify(
                value
            )
        );
    }

    function capitalize(
        value
    ) {

        if (
            !value
        ) {
            return '';
        }

        return (
            value.charAt(0).toUpperCase() +
            value.slice(1)
        );
    }

    function localDateString() {

        const date =
            new Date();

        const year =
            date.getFullYear();

        const month =
            String(
                date.getMonth() + 1
            ).padStart(
                2,
                '0'
            );

        const day =
            String(
                date.getDate()
            ).padStart(
                2,
                '0'
            );

        return (
            `${year}-${month}-${day}`
        );
    }

    function refreshIcons() {

        if (
            window.lucide &&
            typeof window.lucide.createIcons ===
                'function'
        ) {

            window.lucide.createIcons();
        }
    }

    /* ============================================================
     * BUTTON LOADING
     * ============================================================ */

    function setButtonBusy(
        button,
        busy,
        busyText = ''
    ) {

        if (
            !button
        ) {
            return;
        }

        if (
            busy
        ) {

            if (
                !button.dataset.originalHtml
            ) {

                button.dataset.originalHtml =
                    button.innerHTML;
            }

            button.disabled =
                true;

            button.replaceChildren();

            const spinner =
                document.createElement(
                    'span'
                );

            spinner.className =
                'loading-spinner';

            button.appendChild(
                spinner
            );

            if (
                busyText
            ) {

                const text =
                    document.createElement(
                        'span'
                    );

                text.textContent =
                    busyText;

                button.appendChild(
                    text
                );
            }

            return;
        }

        button.disabled =
            false;

        if (
            button.dataset.originalHtml
        ) {

            button.innerHTML =
                button.dataset.originalHtml;

            delete button.dataset.originalHtml;

            refreshIcons();
        }
    }

})();
