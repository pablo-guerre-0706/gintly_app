'use strict';

/**
 * ================================================================
 * GINTLY
 * MOD-03 · INVENTARIO LÓGICO Y BODEGA FÍSICA
 * ================================================================
 *
 * API REAL:
 *
 * GET  /api/v1/stock
 * GET  /api/v1/stock/{product}/{warehouse}
 * PUT  /api/v1/stock/{product}/{warehouse}/thresholds
 *
 * GET  /api/v1/warehouses
 * GET  /api/v1/products
 *
 * POST /api/v1/physical-counts
 *
 * GET  /api/v1/inventory-movements
 *
 * IMPORTANTE:
 *
 * stock_levels NO tiene POST.
 * quantity y reserved_quantity NO son editables desde API.
 *
 * Toda escritura real del saldo la controla InventoryService.
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

    const API = Object.freeze({
        stock:
            `${API_BASE_URL}/api/v1/stock`,

        warehouses:
            `${API_BASE_URL}/api/v1/warehouses`,

        products:
            `${API_BASE_URL}/api/v1/products`,

        physicalCounts:
            `${API_BASE_URL}/api/v1/physical-counts`,

        inventoryMovements:
            `${API_BASE_URL}/api/v1/inventory-movements`,

        logout:
            `${API_BASE_URL}/api/v1/logout`
    });

    /* ============================================================
     * STATE
     * ============================================================ */

    const state = {
        stock: [],
        products: [],
        warehouses: [],

        search: '',
        warehouseId: '',
        belowMin: false,

        currentPage: 1,
        lastPage: 1,
        perPage: 25,
        total: 0,

        loading: false,
        requestController: null,

        metrics: {
            total: 0,
            low: 0,
            movements: 0,
            reserved: '0.000'
        },

        drawer: {
            mode: null,

            productId: null,
            warehouseId: null,

            stockItem: null
        }
    };

    /* ============================================================
     * DOM
     * ============================================================ */

    const dom = {};

    /* ============================================================
     * MOCK DATA
     * ============================================================ */

    const MOCK_DATA = {
        products: [
            {
                id: 1,
                sku: 'ARR-001',
                name: 'Arroz Faisán',
                is_active: true
            },
            {
                id: 2,
                sku: 'ACE-002',
                name: 'Aceite vegetal',
                is_active: true
            },
            {
                id: 3,
                sku: 'LEC-003',
                name: 'Leche entera',
                is_active: true
            },
            {
                id: 4,
                sku: 'CAF-004',
                name: 'Café molido',
                is_active: true
            },
            {
                id: 5,
                sku: 'AZU-005',
                name: 'Azúcar blanca',
                is_active: true
            },
            {
                id: 6,
                sku: 'PAN-006',
                name: 'Pan blanco',
                is_active: true
            }
        ],

        warehouses: [
            {
                id: 1,
                branch_id: 1,
                name: 'Bodega Central',
                is_default: true,
                is_active: true
            },
            {
                id: 2,
                branch_id: 1,
                name: 'Bodega Secundaria',
                is_default: false,
                is_active: true
            },
            {
                id: 3,
                branch_id: 2,
                name: 'Bodega Norte',
                is_default: true,
                is_active: true
            }
        ],

        stock: [
            {
                id: 1,
                product_id: 1,
                warehouse_id: 1,
                quantity: '80.000',
                reserved_quantity: '10.000',
                available: '70.000',
                min_stock: '20.000',
                max_stock: '120.000',
                average_cost: '18.0000'
            },
            {
                id: 2,
                product_id: 2,
                warehouse_id: 1,
                quantity: '15.000',
                reserved_quantity: '4.000',
                available: '11.000',
                min_stock: '20.000',
                max_stock: '80.000',
                average_cost: '45.5000'
            },
            {
                id: 3,
                product_id: 3,
                warehouse_id: 1,
                quantity: '7.000',
                reserved_quantity: '7.000',
                available: '0.000',
                min_stock: '12.000',
                max_stock: '60.000',
                average_cost: '32.0000'
            },
            {
                id: 4,
                product_id: 4,
                warehouse_id: 2,
                quantity: '46.000',
                reserved_quantity: '6.000',
                available: '40.000',
                min_stock: '15.000',
                max_stock: '90.000',
                average_cost: '82.0000'
            },
            {
                id: 5,
                product_id: 5,
                warehouse_id: 2,
                quantity: '12.000',
                reserved_quantity: '2.000',
                available: '10.000',
                min_stock: '16.000',
                max_stock: '50.000',
                average_cost: '19.0000'
            },
            {
                id: 6,
                product_id: 6,
                warehouse_id: 3,
                quantity: '34.000',
                reserved_quantity: '3.000',
                available: '31.000',
                min_stock: '10.000',
                max_stock: '50.000',
                average_cost: '28.0000'
            }
        ],

        movements: [
            {
                id: 1,
                type: 'entrada',
                quantity: '20.000',
                created_at: new Date().toISOString()
            },
            {
                id: 2,
                type: 'salida',
                quantity: '5.000',
                created_at: new Date().toISOString()
            },
            {
                id: 3,
                type: 'ajuste',
                quantity: '2.000',
                created_at: new Date().toISOString()
            },
            {
                id: 4,
                type: 'traspaso',
                quantity: '10.000',
                created_at: new Date().toISOString()
            }
        ],

        physicalCounts: []
    };

    /* ============================================================
     * BOOTSTRAP
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

        await loadMasterData();

        await loadInventory();
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

        /* Metrics */

        dom.metricTotalStock =
            document.getElementById(
                'metric-total-stock'
            );

        dom.metricLowStock =
            document.getElementById(
                'metric-low-stock'
            );

        dom.metricMovements =
            document.getElementById(
                'metric-movements'
            );

        dom.metricReserved =
            document.getElementById(
                'metric-reserved'
            );

        dom.inventoryErrorBanner =
            document.getElementById(
                'inventory-error-banner'
            );

        /* Filters */

        dom.search =
            document.getElementById(
                'inventory-search'
            );

        dom.stockFilterButtons =
            document.getElementById(
                'stock-filter-buttons'
            );

        dom.warehouseFilter =
            document.getElementById(
                'warehouse-filter'
            );

        dom.refreshInventoryBtn =
            document.getElementById(
                'refresh-inventory-btn'
            );

        /* Table */

        dom.tableBody =
            document.getElementById(
                'inventory-table-body'
            );

        dom.inventoryRecordCount =
            document.getElementById(
                'inventory-record-count'
            );

        dom.registerCountBtn =
            document.getElementById(
                'register-count-btn'
            );

        /* Pagination */

        dom.paginationContainer =
            document.getElementById(
                'pagination-container'
            );

        dom.paginationLabel =
            document.getElementById(
                'pagination-label'
            );

        dom.prevPageBtn =
            document.getElementById(
                'prev-page-btn'
            );

        dom.nextPageBtn =
            document.getElementById(
                'next-page-btn'
            );

        /* Drawer */

        dom.drawerOverlay =
            document.getElementById(
                'inventory-drawer-overlay'
            );

        dom.drawer =
            document.getElementById(
                'inventory-drawer'
            );

        dom.drawerForm =
            document.getElementById(
                'inventory-drawer-form'
            );

        dom.drawerEyebrow =
            document.getElementById(
                'drawer-eyebrow'
            );

        dom.drawerTitle =
            document.getElementById(
                'drawer-title'
            );

        dom.closeDrawerBtn =
            document.getElementById(
                'close-drawer-btn'
            );

        dom.cancelDrawerBtn =
            document.getElementById(
                'cancel-drawer-btn'
            );

        dom.saveDrawerBtn =
            document.getElementById(
                'save-drawer-btn'
            );

        dom.drawerGlobalError =
            document.getElementById(
                'drawer-global-error'
            );

        /* Physical count */

        dom.physicalCountFields =
            document.getElementById(
                'physical-count-fields'
            );

        dom.countProductId =
            document.getElementById(
                'count-product-id'
            );

        dom.countWarehouseId =
            document.getElementById(
                'count-warehouse-id'
            );

        dom.countedQuantity =
            document.getElementById(
                'counted-quantity'
            );

        dom.countNotes =
            document.getElementById(
                'count-notes'
            );

        /* Thresholds */

        dom.thresholdFields =
            document.getElementById(
                'threshold-fields'
            );

        dom.thresholdProductName =
            document.getElementById(
                'threshold-product-name'
            );

        dom.thresholdWarehouseName =
            document.getElementById(
                'threshold-warehouse-name'
            );

        dom.thresholdMinStock =
            document.getElementById(
                'threshold-min-stock'
            );

        dom.thresholdMaxStock =
            document.getElementById(
                'threshold-max-stock'
            );
    }

    /* ============================================================
     * EVENTS
     * ============================================================ */

    function bindEvents() {

        /* Search */

        dom.search.addEventListener(
            'input',
            debounce(
                async (event) => {

                    state.search =
                        String(
                            event.target.value ?? ''
                        ).trim();

                    state.currentPage = 1;

                    await loadInventory();
                },
                350
            )
        );

        /* Stock filters */

        dom.stockFilterButtons.addEventListener(
            'click',
            async (event) => {

                const button =
                    event.target.closest(
                        '[data-stock-filter]'
                    );

                if (!button) {
                    return;
                }

                const filter =
                    button.dataset.stockFilter;

                state.belowMin =
                    filter === 'below';

                state.currentPage = 1;

                setActiveStockFilter(
                    button
                );

                await loadInventory();
            }
        );

        /* Warehouse */

        dom.warehouseFilter.addEventListener(
            'change',
            async (event) => {

                state.warehouseId =
                    String(
                        event.target.value ?? ''
                    );

                state.currentPage = 1;

                await loadInventory();
            }
        );

        /* Refresh */

        dom.refreshInventoryBtn.addEventListener(
            'click',
            async () => {

                setButtonBusy(
                    dom.refreshInventoryBtn,
                    true,
                    'Actualizando'
                );

                try {

                    await loadMasterData();

                    await loadInventory();

                } finally {

                    setButtonBusy(
                        dom.refreshInventoryBtn,
                        false
                    );
                }
            }
        );

        /* Register physical count */

        dom.registerCountBtn.addEventListener(
            'click',
            () => {

                openDrawerForPhysicalCount();
            }
        );

        /* Edit threshold */

        dom.tableBody.addEventListener(
            'click',
            (event) => {

                const button =
                    event.target.closest(
                        '[data-action="edit-thresholds"]'
                    );

                if (!button) {
                    return;
                }

                const productId =
                    Number(
                        button.dataset.productId
                    );

                const warehouseId =
                    Number(
                        button.dataset.warehouseId
                    );

                const stockItem =
                    state.stock.find(
                        (item) =>
                            Number(item.product_id) === productId &&
                            Number(item.warehouse_id) === warehouseId
                    );

                if (!stockItem) {
                    return;
                }

                openDrawerForThresholds(
                    stockItem
                );
            }
        );

        /* Drawer close */

        dom.closeDrawerBtn.addEventListener(
            'click',
            closeDrawer
        );

        dom.cancelDrawerBtn.addEventListener(
            'click',
            closeDrawer
        );

        dom.drawerOverlay.addEventListener(
            'click',
            closeDrawer
        );

        /* Drawer submit */

        dom.drawerForm.addEventListener(
            'submit',
            submitDrawer
        );

        /* Clear input errors */

        [
            dom.countProductId,
            dom.countWarehouseId,
            dom.countedQuantity,
            dom.countNotes,
            dom.thresholdMinStock,
            dom.thresholdMaxStock
        ].forEach(
            (element) => {

                const eventName =
                    element.tagName === 'SELECT'
                        ? 'change'
                        : 'input';

                element.addEventListener(
                    eventName,
                    () => {

                        clearDrawerFieldError(
                            element.name
                        );
                    }
                );
            }
        );

        /* Pagination */

        dom.prevPageBtn.addEventListener(
            'click',
            async () => {

                if (
                    state.loading ||
                    state.currentPage <= 1
                ) {
                    return;
                }

                state.currentPage -= 1;

                await loadInventory();
            }
        );

        dom.nextPageBtn.addEventListener(
            'click',
            async () => {

                if (
                    state.loading ||
                    state.currentPage >= state.lastPage
                ) {
                    return;
                }

                state.currentPage += 1;

                await loadInventory();
            }
        );

        /* Dark */

        dom.darkModeToggle.addEventListener(
            'click',
            toggleTheme
        );

        /* Sidebar */

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

        /* Logout */

        dom.logoutBtn.addEventListener(
            'click',
            logout
        );

        /* ESC */

        document.addEventListener(
            'keydown',
            (event) => {

                if (
                    event.key !== 'Escape'
                ) {
                    return;
                }

                if (
                    state.drawer.mode !== null
                ) {
                    closeDrawer();
                    return;
                }

                closeSidebar();
            }
        );
    }

    /* ============================================================
     * MASTER DATA
     * ============================================================ */

    async function loadMasterData() {

        try {

            if (CONFIG_DEV) {

                state.products =
                    cloneArray(
                        MOCK_DATA.products
                    );

                state.warehouses =
                    cloneArray(
                        MOCK_DATA.warehouses
                    );

                renderMasterSelectors();

                return;
            }

            const [
                productsResponse,
                warehousesResponse
            ] = await Promise.all([
                apiFetch(
                    buildUrl(
                        API.products,
                        {
                            per_page: 100,
                            is_active: 1
                        }
                    )
                ),

                apiFetch(
                    buildUrl(
                        API.warehouses,
                        {
                            per_page: 100,
                            is_active: 1
                        }
                    )
                )
            ]);

            state.products =
                unwrapCollection(
                    productsResponse
                );

            state.warehouses =
                unwrapCollection(
                    warehousesResponse
                );

            renderMasterSelectors();

        } catch (error) {

            console.error(
                'No fue posible cargar productos/bodegas:',
                error
            );

            state.products = [];
            state.warehouses = [];

            renderMasterSelectors();

            showInventoryError(
                error?.message ||
                'No fue posible cargar los datos maestros del inventario.'
            );
        }
    }

    function renderMasterSelectors() {

        renderWarehouseFilter();

        renderCountProductSelect();

        renderCountWarehouseSelect();
    }

    function renderWarehouseFilter() {

        const currentValue =
            state.warehouseId;

        dom.warehouseFilter.replaceChildren();

        const all =
            document.createElement(
                'option'
            );

        all.value = '';
        all.textContent =
            'Todas las bodegas';

        dom.warehouseFilter.appendChild(
            all
        );

        for (
            const warehouse
            of state.warehouses
        ) {

            if (
                warehouse?.is_active === false
            ) {
                continue;
            }

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                String(
                    warehouse.id
                );

            option.textContent =
                String(
                    warehouse.name ?? ''
                );

            dom.warehouseFilter.appendChild(
                option
            );
        }

        dom.warehouseFilter.value =
            currentValue;
    }

    function renderCountProductSelect() {

        dom.countProductId.replaceChildren();

        const empty =
            document.createElement(
                'option'
            );

        empty.value = '';
        empty.textContent =
            'Seleccione';

        dom.countProductId.appendChild(
            empty
        );

        for (
            const product
            of state.products
        ) {

            if (
                product?.is_active === false
            ) {
                continue;
            }

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                String(
                    product.id
                );

            option.textContent =
                `${product.sku ?? ''} · ${product.name ?? ''}`;

            dom.countProductId.appendChild(
                option
            );
        }
    }

    function renderCountWarehouseSelect() {

        dom.countWarehouseId.replaceChildren();

        const empty =
            document.createElement(
                'option'
            );

        empty.value = '';
        empty.textContent =
            'Seleccione';

        dom.countWarehouseId.appendChild(
            empty
        );

        for (
            const warehouse
            of state.warehouses
        ) {

            if (
                warehouse?.is_active === false
            ) {
                continue;
            }

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                String(
                    warehouse.id
                );

            option.textContent =
                String(
                    warehouse.name ?? ''
                );

            dom.countWarehouseId.appendChild(
                option
            );
        }
    }
/* ============================================================
     * LOAD INVENTORY
     * ============================================================ */

    async function loadInventory() {

        if (
            state.requestController
        ) {
            state.requestController.abort();
        }

        state.requestController =
            new AbortController();

        hideInventoryError();

        setLoading(
            true
        );

        renderLoadingRow();

        try {

            if (CONFIG_DEV) {

                await delay(
                    220
                );

                const filtered =
                    filterMockStock();

                state.stock =
                    filtered;

                state.total =
                    filtered.length;

                state.currentPage = 1;
                state.lastPage = 1;

                state.metrics.total =
                    filterMockStockBase().length;

                state.metrics.low =
                    filterMockStockBase()
                        .filter(
                            isBelowMinimum
                        )
                        .length;

                state.metrics.movements =
                    countMockMovementsToday();

                state.metrics.reserved =
                    sumDecimalValues(
                        state.stock.map(
                            (item) =>
                                item.reserved_quantity
                        ),
                        3
                    );

                renderInventory();

                renderMetrics();

                renderPagination();

                return;
            }

            const query = {
                page:
                    state.currentPage,

                per_page:
                    state.perPage
            };

            if (
                state.search !== ''
            ) {
                query.search =
                    state.search;
            }

            if (
                state.warehouseId !== ''
            ) {
                query.warehouse_id =
                    state.warehouseId;
            }

            if (
                state.belowMin
            ) {
                query.below_min = 1;
            }

            const response =
                await apiFetch(
                    buildUrl(
                        API.stock,
                        query
                    ),
                    {
                        signal:
                            state.requestController.signal
                    }
                );

            state.stock =
                Array.isArray(
                    response?.data
                )
                    ? response.data
                    : [];

            const meta =
                response?.meta ?? {};

            state.currentPage =
                positiveInteger(
                    meta.current_page,
                    state.currentPage
                );

            state.lastPage =
                positiveInteger(
                    meta.last_page,
                    1
                );

            state.perPage =
                positiveInteger(
                    meta.per_page,
                    state.perPage
                );

            state.total =
                nonNegativeInteger(
                    meta.total,
                    state.stock.length
                );

            state.metrics.total =
                state.total;

            state.metrics.reserved =
                sumDecimalValues(
                    state.stock.map(
                        (item) =>
                            item.reserved_quantity ??
                            '0.000'
                    ),
                    3
                );

            await loadRemoteMetrics();

            renderInventory();

            renderMetrics();

            renderPagination();

        } catch (error) {

            if (
                error?.name === 'AbortError'
            ) {
                return;
            }

            console.error(
                'Error cargando inventario:',
                error
            );

            state.stock = [];
            state.total = 0;

            renderInventoryErrorRow(
                error
            );

            showInventoryError(
                error?.message ||
                'No fue posible cargar el inventario.'
            );

            renderMetrics();

            renderPagination();

        } finally {

            setLoading(
                false
            );
        }
    }

    /* ============================================================
     * REMOTE METRICS
     * ============================================================ */

    async function loadRemoteMetrics() {

        try {

            const today =
                localDateString();

            const [
                lowResponse,
                movementResponse
            ] = await Promise.all([
                apiFetch(
                    buildUrl(
                        API.stock,
                        {
                            below_min: 1,
                            per_page: 1
                        }
                    )
                ),

                apiFetch(
                    buildUrl(
                        API.inventoryMovements,
                        {
                            from: today,
                            to: today,
                            per_page: 1
                        }
                    )
                )
            ]);

            state.metrics.low =
                nonNegativeInteger(
                    lowResponse?.meta?.total,
                    Array.isArray(
                        lowResponse?.data
                    )
                        ? lowResponse.data.length
                        : 0
                );

            state.metrics.movements =
                nonNegativeInteger(
                    movementResponse?.meta?.total,
                    Array.isArray(
                        movementResponse?.data
                    )
                        ? movementResponse.data.length
                        : 0
                );

        } catch (error) {

            console.warn(
                'Las métricas auxiliares no pudieron cargarse:',
                error
            );

            state.metrics.low =
                state.stock.filter(
                    isBelowMinimum
                ).length;

            state.metrics.movements =
                0;
        }
    }

    /* ============================================================
     * MOCK FILTER
     * ============================================================ */

    function filterMockStockBase() {

        return MOCK_DATA.stock.filter(
            (item) => {

                if (
                    state.warehouseId !== '' &&
                    String(item.warehouse_id) !==
                    state.warehouseId
                ) {
                    return false;
                }

                return true;
            }
        );
    }

    function filterMockStock() {

        const normalizedSearch =
            normalizeText(
                state.search
            );

        return filterMockStockBase()
            .filter(
                (item) => {

                    if (
                        state.belowMin &&
                        !isBelowMinimum(item)
                    ) {
                        return false;
                    }

                    if (
                        normalizedSearch === ''
                    ) {
                        return true;
                    }

                    const product =
                        getProduct(
                            item.product_id
                        );

                    const text =
                        normalizeText(
                            `${product?.sku ?? ''} ${product?.name ?? ''}`
                        );

                    return text.includes(
                        normalizedSearch
                    );
                }
            );
    }

    function countMockMovementsToday() {

        const today =
            localDateString();

        return MOCK_DATA.movements.filter(
            (movement) => {

                const date =
                    String(
                        movement.created_at ??
                        ''
                    ).slice(0, 10);

                return date === today;
            }
        ).length;
    }

    /* ============================================================
     * RENDER METRICS
     * ============================================================ */

    function renderMetrics() {

        dom.metricTotalStock.textContent =
            String(
                state.metrics.total
            );

        dom.metricLowStock.textContent =
            String(
                state.metrics.low
            );

        dom.metricMovements.textContent =
            String(
                state.metrics.movements
            );

        dom.metricReserved.textContent =
            formatQuantity(
                state.metrics.reserved
            );
    }

    /* ============================================================
     * RENDER INVENTORY
     * ============================================================ */

    function renderInventory() {

        dom.tableBody.replaceChildren();

        if (
            state.stock.length === 0
        ) {

            renderEmptyRow();

            updateRecordCount();

            refreshIcons();

            return;
        }

        const fragment =
            document.createDocumentFragment();

        for (
            const stockItem
            of state.stock
        ) {

            fragment.appendChild(
                createInventoryRow(
                    stockItem
                )
            );
        }

        dom.tableBody.appendChild(
            fragment
        );

        updateRecordCount();

        refreshIcons();
    }

    function createInventoryRow(
        stockItem
    ) {

        const product =
            resolveProductFromStock(
                stockItem
            );

        const warehouse =
            resolveWarehouseFromStock(
                stockItem
            );

        const status =
            getStockStatus(
                stockItem
            );

        const row =
            document.createElement(
                'tr'
            );

        row.className =
            'inventory-row h-[68px] border-b border-[#e2e2e2] bg-white ' +
            'last:border-b-0 dark:border-slate-700 dark:bg-slate-900';

        appendCell(
            row,
            product.sku,
            'px-[20px] text-[12px] font-semibold text-[#222222] dark:text-white'
        );

        appendCell(
            row,
            product.name,
            'px-[14px] text-[12px] text-[#444444] dark:text-slate-200'
        );

        appendCell(
            row,
            warehouse.name,
            'px-[14px] text-[12px] text-[#555555] dark:text-slate-300'
        );

        appendCell(
            row,
            formatQuantity(
                stockItem.quantity
            ),
            'px-[14px] text-[12px] font-semibold text-[#292929] dark:text-white'
        );

        appendCell(
            row,
            formatQuantity(
                stockItem.reserved_quantity
            ),
            'px-[14px] text-[12px] text-[#777777] dark:text-slate-300'
        );

        appendCell(
            row,
            formatQuantity(
                stockItem.available
            ),
            'px-[14px] text-[12px] font-bold text-[#292929] dark:text-white'
        );

        appendCell(
            row,
            stockItem.min_stock === null ||
            stockItem.min_stock === undefined
                ? '—'
                : formatQuantity(
                    stockItem.min_stock
                ),
            'px-[14px] text-[12px] text-[#555555] dark:text-slate-300'
        );

        appendStatusCell(
            row,
            status
        );

        const actionCell =
            document.createElement(
                'td'
            );

        actionCell.className =
            'px-[14px]';

        const button =
            document.createElement(
                'button'
            );

        button.type = 'button';

        button.dataset.action =
            'edit-thresholds';

        button.dataset.productId =
            String(
                stockItem.product_id
            );

        button.dataset.warehouseId =
            String(
                stockItem.warehouse_id
            );

        button.className =
            'inline-flex h-[32px] items-center justify-center rounded-[6px] ' +
            'border border-gintly-primary bg-white px-[10px] text-[10px] ' +
            'font-medium text-[#12617a] transition hover:bg-cyan-50 ' +
            'dark:bg-slate-900 dark:text-cyan-300';

        button.textContent =
            'Editar';

        actionCell.appendChild(
            button
        );

        row.appendChild(
            actionCell
        );

        return row;
    }

    function appendCell(
        row,
        value,
        classes
    ) {

        const cell =
            document.createElement(
                'td'
            );

        cell.className =
            classes;

        cell.textContent =
            String(
                value ?? ''
            );

        row.appendChild(
            cell
        );
    }

    function appendStatusCell(
        row,
        status
    ) {

        const cell =
            document.createElement(
                'td'
            );

        cell.className =
            'px-[14px]';

        const badge =
            document.createElement(
                'span'
            );

        badge.className =
            'inline-flex min-h-[27px] items-center rounded-[5px] border px-[8px] ' +
            'text-[10px] font-medium ' +
            status.classes;

        badge.textContent =
            status.label;

        cell.appendChild(
            badge
        );

        row.appendChild(
            cell
        );
    }

    /* ============================================================
     * STOCK STATUS
     * ============================================================ */

    function getStockStatus(
        stockItem
    ) {

        if (
            decimalCompare(
                stockItem.available,
                '0.000',
                3
            ) <= 0
        ) {

            return {
                label:
                    'Agotado',

                classes:
                    'border-red-300 bg-red-50 text-red-600 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300'
            };
        }

        if (
            isBelowMinimum(
                stockItem
            )
        ) {

            return {
                label:
                    'Stock bajo',

                classes:
                    'border-orange-300 bg-orange-50 text-orange-600 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300'
            };
        }

        return {
            label:
                'Disponible',

            classes:
                'border-green-300 bg-green-50 text-green-600 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300'
        };
    }

    function isBelowMinimum(
        stockItem
    ) {

        if (
            stockItem.min_stock === null ||
            stockItem.min_stock === undefined ||
            stockItem.min_stock === ''
        ) {
            return false;
        }

        return (
            decimalCompare(
                stockItem.available,
                stockItem.min_stock,
                3
            ) <= 0
        );
    }

    /* ============================================================
     * PRODUCT / WAREHOUSE RESOLUTION
     * ============================================================ */

    function resolveProductFromStock(
        stockItem
    ) {

        if (
            stockItem?.product
        ) {

            return {
                id:
                    Number(
                        stockItem.product.id ??
                        stockItem.product_id
                    ),

                sku:
                    String(
                        stockItem.product.sku ??
                        ''
                    ),

                name:
                    String(
                        stockItem.product.name ??
                        'Producto'
                    )
            };
        }

        const product =
            getProduct(
                stockItem.product_id
            );

        return {
            id:
                Number(
                    stockItem.product_id
                ),

            sku:
                String(
                    product?.sku ??
                    `#${stockItem.product_id}`
                ),

            name:
                String(
                    product?.name ??
                    'Producto'
                )
        };
    }

    function resolveWarehouseFromStock(
        stockItem
    ) {

        if (
            stockItem?.warehouse
        ) {

            return {
                id:
                    Number(
                        stockItem.warehouse.id ??
                        stockItem.warehouse_id
                    ),

                name:
                    String(
                        stockItem.warehouse.name ??
                        'Bodega'
                    )
            };
        }

        const warehouse =
            getWarehouse(
                stockItem.warehouse_id
            );

        return {
            id:
                Number(
                    stockItem.warehouse_id
                ),

            name:
                String(
                    warehouse?.name ??
                    `Bodega #${stockItem.warehouse_id}`
                )
        };
    }

    function getProduct(
        id
    ) {

        return state.products.find(
            (product) =>
                Number(product.id) ===
                Number(id)
        ) ?? null;
    }

    function getWarehouse(
        id
    ) {

        return state.warehouses.find(
            (warehouse) =>
                Number(warehouse.id) ===
                Number(id)
        ) ?? null;
    }

    /* ============================================================
     * EMPTY / LOADING / ERROR ROWS
     * ============================================================ */

    function renderLoadingRow() {

        dom.tableBody.replaceChildren();

        const row =
            document.createElement(
                'tr'
            );

        const cell =
            document.createElement(
                'td'
            );

        cell.colSpan = 9;

        cell.className =
            'h-[130px] px-6 text-center text-[12px] text-[#777777]';

        const container =
            document.createElement(
                'div'
            );

        container.className =
            'flex items-center justify-center gap-3';

        const spinner =
            document.createElement(
                'span'
            );

        spinner.className =
            'loading-spinner';

        const text =
            document.createElement(
                'span'
            );

        text.textContent =
            'Cargando inventario...';

        container.append(
            spinner,
            text
        );

        cell.appendChild(
            container
        );

        row.appendChild(
            cell
        );

        dom.tableBody.appendChild(
            row
        );
    }

    function renderEmptyRow() {

        const row =
            document.createElement(
                'tr'
            );

        const cell =
            document.createElement(
                'td'
            );

        cell.colSpan = 9;

        cell.className =
            'h-[145px] px-6 text-center text-[#777777] dark:text-slate-400';

        const container =
            document.createElement(
                'div'
            );

        container.className =
            'flex flex-col items-center justify-center gap-2';

        const icon =
            document.createElement(
                'i'
            );

        icon.dataset.lucide =
            'package-search';

        icon.className =
            'h-7 w-7';

        const title =
            document.createElement(
                'strong'
            );

        title.className =
            'text-[13px] text-[#494949] dark:text-slate-200';

        title.textContent =
            'No se encontraron saldos de inventario';

        const detail =
            document.createElement(
                'span'
            );

        detail.className =
            'text-[11px]';

        detail.textContent =
            'Modifica los filtros o verifica que existan movimientos de inventario.';

        container.append(
            icon,
            title,
            detail
        );

        cell.appendChild(
            container
        );

        row.appendChild(
            cell
        );

        dom.tableBody.appendChild(
            row
        );
    }

    function renderInventoryErrorRow(
        error
    ) {

        dom.tableBody.replaceChildren();

        const row =
            document.createElement(
                'tr'
            );

        const cell =
            document.createElement(
                'td'
            );

        cell.colSpan = 9;

        cell.className =
            'border-y border-red-200 bg-red-50 px-6 py-7 text-center text-[12px] ' +
            'text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300';

        if (
            error?.status === 403
        ) {

            cell.textContent =
                'No tienes permisos para consultar el inventario.';

        } else if (
            error?.status === 500
        ) {

            cell.textContent =
                'El servidor no pudo cargar el inventario.';

        } else {

            cell.textContent =
                error?.message ||
                'No fue posible cargar el inventario.';
        }

        row.appendChild(
            cell
        );

        dom.tableBody.appendChild(
            row
        );

        refreshIcons();
    }

    function updateRecordCount() {

        const total =
            state.total;

        dom.inventoryRecordCount.textContent =
            `${total} ${
                total === 1
                    ? 'registro'
                    : 'registros'
            }`;
    }
/* ============================================================
     * PAGINATION
     * ============================================================ */

    function renderPagination() {

        const visible =
            state.lastPage > 1;

        dom.paginationContainer.classList.toggle(
            'hidden',
            !visible
        );

        dom.paginationContainer.classList.toggle(
            'flex',
            visible
        );

        if (!visible) {
            return;
        }

        dom.paginationLabel.textContent =
            `Página ${state.currentPage} de ${state.lastPage}`;

        dom.prevPageBtn.disabled =
            state.loading ||
            state.currentPage <= 1;

        dom.nextPageBtn.disabled =
            state.loading ||
            state.currentPage >= state.lastPage;
    }

    /* ============================================================
     * STOCK FILTER UI
     * ============================================================ */

    function setActiveStockFilter(
        activeButton
    ) {

        const buttons =
            dom.stockFilterButtons.querySelectorAll(
                '[data-stock-filter]'
            );

        for (
            const button
            of buttons
        ) {

            button.setAttribute(
                'aria-pressed',
                button === activeButton
                    ? 'true'
                    : 'false'
            );
        }
    }

    /* ============================================================
     * DRAWER · PHYSICAL COUNT
     * ============================================================ */

    function openDrawerForPhysicalCount() {

        resetDrawer();

        state.drawer.mode =
            'physical-count';

        dom.drawerEyebrow.textContent =
            'Nuevo registro';

        dom.drawerTitle.textContent =
            'Registrar conteo físico';

        dom.physicalCountFields.classList.remove(
            'hidden'
        );

        dom.thresholdFields.classList.add(
            'hidden'
        );

        openDrawer();
    }

    /* ============================================================
     * DRAWER · THRESHOLDS
     * ============================================================ */

    function openDrawerForThresholds(
        stockItem
    ) {

        resetDrawer();

        state.drawer.mode =
            'thresholds';

        state.drawer.productId =
            Number(
                stockItem.product_id
            );

        state.drawer.warehouseId =
            Number(
                stockItem.warehouse_id
            );

        state.drawer.stockItem =
            stockItem;

        const product =
            resolveProductFromStock(
                stockItem
            );

        const warehouse =
            resolveWarehouseFromStock(
                stockItem
            );

        dom.drawerEyebrow.textContent =
            'Configuración de inventario';

        dom.drawerTitle.textContent =
            'Editar umbrales';

        dom.physicalCountFields.classList.add(
            'hidden'
        );

        dom.thresholdFields.classList.remove(
            'hidden'
        );

        dom.thresholdProductName.textContent =
            `${product.sku} · ${product.name}`;

        dom.thresholdWarehouseName.textContent =
            warehouse.name;

        dom.thresholdMinStock.value =
            stockItem.min_stock ??
            '';

        dom.thresholdMaxStock.value =
            stockItem.max_stock ??
            '';

        openDrawer();
    }

    function openDrawer() {

        dom.drawerOverlay.classList.remove(
            'hidden'
        );

        document.body.classList.add(
            'overflow-hidden'
        );

        requestAnimationFrame(
            () => {

                dom.drawerOverlay.classList.remove(
                    'opacity-0'
                );

                dom.drawer.classList.remove(
                    'translate-x-full'
                );
            }
        );

        refreshIcons();
    }

    function closeDrawer() {

        dom.drawer.classList.add(
            'translate-x-full'
        );

        dom.drawerOverlay.classList.add(
            'opacity-0'
        );

        window.setTimeout(
            () => {

                dom.drawerOverlay.classList.add(
                    'hidden'
                );

                document.body.classList.remove(
                    'overflow-hidden'
                );

                resetDrawer();

            },
            200
        );
    }

    function resetDrawer() {

        state.drawer.mode =
            null;

        state.drawer.productId =
            null;

        state.drawer.warehouseId =
            null;

        state.drawer.stockItem =
            null;

        dom.drawerForm.reset();

        clearDrawerErrors();

        dom.physicalCountFields.classList.remove(
            'hidden'
        );

        dom.thresholdFields.classList.add(
            'hidden'
        );

        dom.thresholdProductName.textContent =
            '—';

        dom.thresholdWarehouseName.textContent =
            '—';
    }

    /* ============================================================
     * DRAWER SUBMIT
     * ============================================================ */

    async function submitDrawer(
        event
    ) {

        event.preventDefault();

        clearDrawerErrors();

        if (
            state.drawer.mode ===
            'physical-count'
        ) {

            await submitPhysicalCount();

            return;
        }

        if (
            state.drawer.mode ===
            'thresholds'
        ) {

            await submitThresholds();
        }
    }

    /* ============================================================
     * SUBMIT PHYSICAL COUNT
     * ============================================================ */

    async function submitPhysicalCount() {

        const payload = {
            product_id:
                dom.countProductId.value
                    ? Number(
                        dom.countProductId.value
                    )
                    : null,

            warehouse_id:
                dom.countWarehouseId.value
                    ? Number(
                        dom.countWarehouseId.value
                    )
                    : null,

            counted_quantity:
                normalizeDecimalInput(
                    dom.countedQuantity.value
                ),

            notes:
                dom.countNotes.value.trim() ||
                null
        };

        if (
            !validatePhysicalCount(
                payload
            )
        ) {
            return;
        }

        setButtonBusy(
            dom.saveDrawerBtn,
            true,
            'Guardando'
        );

        try {

            if (CONFIG_DEV) {

                await delay(
                    300
                );

                const currentStock =
                    MOCK_DATA.stock.find(
                        (item) =>
                            Number(item.product_id) === payload.product_id &&
                            Number(item.warehouse_id) === payload.warehouse_id
                    );

                const physicalCount = {
                    id:
                        MOCK_DATA.physicalCounts.length + 1,

                    product_id:
                        payload.product_id,

                    warehouse_id:
                        payload.warehouse_id,

                    counted_quantity:
                        normalizeDecimalScale(
                            payload.counted_quantity,
                            3
                        ),

                    system_quantity:
                        currentStock?.quantity ??
                        '0.000',

                    notes:
                        payload.notes,

                    status:
                        'abierto'
                };

                MOCK_DATA.physicalCounts.push(
                    physicalCount
                );

                window.alert(
                    'Conteo físico registrado correctamente. El stock no se modifica hasta aplicar el conteo.'
                );

                closeDrawer();

                return;
            }

            await apiFetch(
                API.physicalCounts,
                {
                    method:
                        'POST',

                    headers: {
                        'Content-Type':
                            'application/json'
                    },

                    body:
                        JSON.stringify(
                            payload
                        )
                }
            );

            window.alert(
                'Conteo físico registrado correctamente.'
            );

            closeDrawer();

        } catch (error) {

            console.error(
                'Error registrando conteo físico:',
                error
            );

            handleDrawerApiError(
                error
            );

        } finally {

            setButtonBusy(
                dom.saveDrawerBtn,
                false
            );
        }
    }

    function validatePhysicalCount(
        payload
    ) {

        let valid =
            true;

        if (
            !payload.product_id
        ) {

            setDrawerFieldError(
                'product_id',
                'Selecciona un producto.'
            );

            valid =
                false;
        }

        if (
            !payload.warehouse_id
        ) {

            setDrawerFieldError(
                'warehouse_id',
                'Selecciona una bodega.'
            );

            valid =
                false;
        }

        if (
            payload.counted_quantity === '' ||
            !isValidQuantity(
                payload.counted_quantity
            )
        ) {

            setDrawerFieldError(
                'counted_quantity',
                'Ingresa una cantidad válida.'
            );

            valid =
                false;
        }

        if (
            payload.notes !== null &&
            payload.notes.length > 500
        ) {

            setDrawerFieldError(
                'notes',
                'Las notas no pueden superar 500 caracteres.'
            );

            valid =
                false;
        }

        return valid;
    }

    /* ============================================================
     * SUBMIT THRESHOLDS
     * ============================================================ */

    async function submitThresholds() {

        const minStock =
            normalizeNullableDecimalInput(
                dom.thresholdMinStock.value
            );

        const maxStock =
            normalizeNullableDecimalInput(
                dom.thresholdMaxStock.value
            );

        const payload = {
            min_stock:
                minStock,

            max_stock:
                maxStock
        };

        if (
            !validateThresholds(
                payload
            )
        ) {
            return;
        }

        setButtonBusy(
            dom.saveDrawerBtn,
            true,
            'Guardando'
        );

        try {

            if (CONFIG_DEV) {

                await delay(
                    280
                );

                const stockItem =
                    MOCK_DATA.stock.find(
                        (item) =>
                            Number(item.product_id) ===
                                state.drawer.productId &&
                            Number(item.warehouse_id) ===
                                state.drawer.warehouseId
                    );

                if (!stockItem) {

                    throw new Error(
                        'No se encontró el saldo de inventario.'
                    );
                }

                stockItem.min_stock =
                    payload.min_stock === null
                        ? null
                        : normalizeDecimalScale(
                            payload.min_stock,
                            3
                        );

                stockItem.max_stock =
                    payload.max_stock === null
                        ? null
                        : normalizeDecimalScale(
                            payload.max_stock,
                            3
                        );

                window.alert(
                    'Umbrales actualizados correctamente.'
                );

                closeDrawer();

                await loadInventory();

                return;
            }

            const url =
                `${API.stock}/${state.drawer.productId}/${state.drawer.warehouseId}/thresholds`;

            await apiFetch(
                url,
                {
                    method:
                        'PUT',

                    headers: {
                        'Content-Type':
                            'application/json'
                    },

                    body:
                        JSON.stringify(
                            payload
                        )
                }
            );

            window.alert(
                'Umbrales actualizados correctamente.'
            );

            closeDrawer();

            await loadInventory();

        } catch (error) {

            console.error(
                'Error actualizando umbrales:',
                error
            );

            handleDrawerApiError(
                error
            );

        } finally {

            setButtonBusy(
                dom.saveDrawerBtn,
                false
            );
        }
    }

    function validateThresholds(
        payload
    ) {

        let valid =
            true;

        if (
            payload.min_stock !== null &&
            !isValidQuantity(
                payload.min_stock
            )
        ) {

            setDrawerFieldError(
                'min_stock',
                'El stock mínimo debe ser una cantidad válida.'
            );

            valid =
                false;
        }

        if (
            payload.max_stock !== null &&
            !isValidQuantity(
                payload.max_stock
            )
        ) {

            setDrawerFieldError(
                'max_stock',
                'El stock máximo debe ser una cantidad válida.'
            );

            valid =
                false;
        }

        if (
            payload.min_stock !== null &&
            payload.max_stock !== null &&
            decimalCompare(
                payload.min_stock,
                payload.max_stock,
                3
            ) > 0
        ) {

            setDrawerFieldError(
                'max_stock',
                'El stock máximo no puede ser menor que el mínimo.'
            );

            valid =
                false;
        }

        return valid;
    }

    /* ============================================================
     * DRAWER ERRORS
     * ============================================================ */

    function setDrawerFieldError(
        field,
        message
    ) {

        const error =
            document.querySelector(
                `[data-drawer-error="${cssEscape(field)}"]`
            );

        if (error) {

            error.textContent =
                message;

            error.classList.remove(
                'hidden'
            );
        }

        const control =
            getDrawerControl(
                field
            );

        if (control) {

            control.classList.remove(
                'border-[#c9c9c9]'
            );

            control.classList.add(
                'border-red-500',
                'ring-1',
                'ring-red-200'
            );
        }
    }

    function clearDrawerFieldError(
        field
    ) {

        const error =
            document.querySelector(
                `[data-drawer-error="${cssEscape(field)}"]`
            );

        if (error) {

            error.textContent = '';

            error.classList.add(
                'hidden'
            );
        }

        const control =
            getDrawerControl(
                field
            );

        if (control) {

            control.classList.remove(
                'border-red-500',
                'ring-1',
                'ring-red-200'
            );

            control.classList.add(
                'border-[#c9c9c9]'
            );
        }
    }

    function clearDrawerErrors() {

        const fields = [
            'product_id',
            'warehouse_id',
            'counted_quantity',
            'notes',
            'min_stock',
            'max_stock'
        ];

        for (
            const field
            of fields
        ) {

            clearDrawerFieldError(
                field
            );
        }

        dom.drawerGlobalError.textContent =
            '';

        dom.drawerGlobalError.classList.add(
            'hidden'
        );
    }

    function getDrawerControl(
        field
    ) {

        switch (field) {

            case 'product_id':
                return dom.countProductId;

            case 'warehouse_id':
                return dom.countWarehouseId;

            case 'counted_quantity':
                return dom.countedQuantity;

            case 'notes':
                return dom.countNotes;

            case 'min_stock':
                return dom.thresholdMinStock;

            case 'max_stock':
                return dom.thresholdMaxStock;

            default:
                return null;
        }
    }

    function showDrawerGlobalError(
        message
    ) {

        dom.drawerGlobalError.textContent =
            message;

        dom.drawerGlobalError.classList.remove(
            'hidden'
        );
    }

    function handleDrawerApiError(
        error
    ) {

        if (
            error?.status === 422 &&
            error?.payload?.errors &&
            typeof error.payload.errors ===
                'object'
        ) {

            for (
                const [
                    field,
                    messages
                ]
                of Object.entries(
                    error.payload.errors
                )
            ) {

                const message =
                    Array.isArray(
                        messages
                    )
                        ? messages[0]
                        : String(
                            messages
                        );

                setDrawerFieldError(
                    field,
                    message
                );
            }

            showDrawerGlobalError(
                'Revisa los campos marcados.'
            );

            return;
        }

        if (
            error?.status === 403
        ) {

            showDrawerGlobalError(
                'No tienes permisos para realizar esta operación.'
            );

            return;
        }

        if (
            error?.status === 409
        ) {

            showDrawerGlobalError(
                error?.message ||
                'La operación entra en conflicto con el estado actual del inventario.'
            );

            return;
        }

        if (
            error?.status === 500
        ) {

            showDrawerGlobalError(
                'El servidor no pudo completar la operación.'
            );

            return;
        }

        showDrawerGlobalError(
            error?.message ||
            'No fue posible completar la operación.'
        );
    }

    /* ============================================================
     * API FETCH
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

        if (token) {

            headers.set(
                'Authorization',
                `Bearer ${token}`
            );
        }

        /*
         * Compatibilidad adicional si Sanctum funciona
         * mediante cookie SPA en el mismo origen.
         */
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
            response.status === 204
        ) {
            return null;
        }

        const contentType =
            response.headers.get(
                'content-type'
            ) ?? '';

        if (
            !contentType.includes(
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
                    'No tienes permisos para realizar esta operación.'
                );

            case 404:
                return (
                    'El recurso solicitado no existe.'
                );

            case 409:
                return (
                    'La operación entra en conflicto con el estado actual del inventario.'
                );

            case 422:
                return (
                    'Los datos enviados no superaron la validación.'
                );

            case 429:
                return (
                    'Se realizaron demasiadas solicitudes.'
                );

            case 500:
                return (
                    'Ocurrió un error interno en el servidor.'
                );

            default:
                return (
                    `No fue posible completar la solicitud HTTP (${status}).`
                );
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

        const cookies =
            document.cookie
                .split(';');

        for (
            const rawCookie
            of cookies
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

    /* ============================================================
     * LOGOUT
     * ============================================================ */

    async function logout() {

        setButtonBusy(
            dom.logoutBtn,
            true,
            'Saliendo'
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
                'Error cerrando sesión:',
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
     * GENERAL ERROR BANNER
     * ============================================================ */

    function showInventoryError(
        message
    ) {

        dom.inventoryErrorBanner.textContent =
            message;

        dom.inventoryErrorBanner.classList.remove(
            'hidden'
        );
    }

    function hideInventoryError() {

        dom.inventoryErrorBanner.textContent =
            '';

        dom.inventoryErrorBanner.classList.add(
            'hidden'
        );
    }

    /* ============================================================
     * DARK MODE
     * ============================================================ */

    function restoreTheme() {

        const theme =
            localStorage.getItem(
                'gintly_theme'
            );

        applyTheme(
            theme === 'dark'
        );
    }

    function toggleTheme() {

        const currentlyDark =
            document.documentElement
                .classList
                .contains(
                    'dark'
                );

        const dark =
            !currentlyDark;

        applyTheme(
            dark
        );

        localStorage.setItem(
            'gintly_theme',
            dark
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

        if (
            state.drawer.mode === null
        ) {

            document.body.classList.remove(
                'overflow-hidden'
            );
        }
    }

    /* ============================================================
     * DECIMAL MATH
     * ============================================================ */

    /**
     * Convierte un decimal string en BigInt escalado.
     *
     * Ej:
     * decimalToScaledBigInt("10.250", 3)
     * => 10250n
     */
    function decimalToScaledBigInt(
        value,
        scale
    ) {

        const text =
            String(
                value ?? '0'
            ).trim();

        const match =
            text.match(
                /^(-?)(\d+)(?:\.(\d+))?$/
            );

        if (!match) {

            throw new Error(
                `Decimal inválido: ${text}`
            );
        }

        const negative =
            match[1] === '-';

        const integer =
            match[2];

        const fraction =
            String(
                match[3] ?? ''
            )
                .slice(
                    0,
                    scale
                )
                .padEnd(
                    scale,
                    '0'
                );

        const digits =
            `${integer}${fraction}`
                .replace(
                    /^0+(?=\d)/,
                    ''
                ) || '0';

        const bigint =
            BigInt(
                digits
            );

        return negative
            ? -bigint
            : bigint;
    }

    function scaledBigIntToDecimal(
        value,
        scale
    ) {

        const negative =
            value < 0n;

        const absolute =
            negative
                ? -value
                : value;

        const text =
            absolute
                .toString()
                .padStart(
                    scale + 1,
                    '0'
                );

        const integerPart =
            scale === 0
                ? text
                : text.slice(
                    0,
                    -scale
                );

        const fraction =
            scale === 0
                ? ''
                : text.slice(
                    -scale
                );

        return (
            `${negative ? '-' : ''}` +
            integerPart +
            (
                scale > 0
                    ? `.${fraction}`
                    : ''
            )
        );
    }

    function decimalCompare(
        left,
        right,
        scale
    ) {

        const a =
            decimalToScaledBigInt(
                left ?? '0',
                scale
            );

        const b =
            decimalToScaledBigInt(
                right ?? '0',
                scale
            );

        if (
            a < b
        ) {
            return -1;
        }

        if (
            a > b
        ) {
            return 1;
        }

        return 0;
    }

    function sumDecimalValues(
        values,
        scale
    ) {

        let total =
            0n;

        for (
            const value
            of values
        ) {

            try {

                total +=
                    decimalToScaledBigInt(
                        value ?? '0',
                        scale
                    );

            } catch {

                continue;
            }
        }

        return scaledBigIntToDecimal(
            total,
            scale
        );
    }

    /* ============================================================
     * DECIMAL INPUT
     * ============================================================ */

    function normalizeDecimalInput(
        value
    ) {

        return String(
            value ?? ''
        )
            .trim()
            .replace(
                /,/g,
                ''
            );
    }

    function normalizeNullableDecimalInput(
        value
    ) {

        const normalized =
            normalizeDecimalInput(
                value
            );

        return normalized === ''
            ? null
            : normalized;
    }

    function normalizeDecimalScale(
        value,
        scale
    ) {

        const scaled =
            decimalToScaledBigInt(
                value,
                scale
            );

        return scaledBigIntToDecimal(
            scaled,
            scale
        );
    }

    function isValidQuantity(
        value
    ) {

        const text =
            String(
                value ?? ''
            );

        if (
            !/^(?:0|[1-9]\d*)(?:\.\d{1,3})?$/
                .test(text)
        ) {
            return false;
        }

        return (
            decimalCompare(
                text,
                '0.000',
                3
            ) >= 0
        );
    }

    function formatQuantity(
        value
    ) {

        const text =
            String(
                value ?? '0'
            );

        const match =
            text.match(
                /^(-?\d+)(?:\.(\d+))?$/
            );

        if (!match) {
            return text;
        }

        const integer =
            match[1];

        const fraction =
            String(
                match[2] ?? ''
            )
                .slice(
                    0,
                    3
                )
                .replace(
                    /0+$/,
                    ''
                );

        return fraction
            ? `${integer}.${fraction}`
            : integer;
    }

    /* ============================================================
     * UTILS
     * ============================================================ */

    function unwrapCollection(
        payload
    ) {

        if (
            Array.isArray(
                payload
            )
        ) {
            return payload;
        }

        if (
            Array.isArray(
                payload?.data
            )
        ) {
            return payload.data;
        }

        return [];
    }

    function buildUrl(
        baseUrl,
        params = {}
    ) {

        const query =
            new URLSearchParams();

        for (
            const [
                key,
                value
            ]
            of Object.entries(
                params
            )
        ) {

            if (
                value === undefined ||
                value === null ||
                value === ''
            ) {
                continue;
            }

            query.set(
                key,
                String(value)
            );
        }

        const serialized =
            query.toString();

        return serialized
            ? `${baseUrl}?${serialized}`
            : baseUrl;
    }

    function cloneArray(
        source
    ) {

        return source.map(
            (item) => ({
                ...item
            })
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
        delayMilliseconds
    ) {

        let timeoutId =
            null;

        return (...args) => {

            window.clearTimeout(
                timeoutId
            );

            timeoutId =
                window.setTimeout(
                    () => {

                        callback(
                            ...args
                        );

                    },
                    delayMilliseconds
                );
        };
    }

    function positiveInteger(
        value,
        fallback
    ) {

        const parsed =
            Number(
                value
            );

        return (
            Number.isInteger(
                parsed
            ) &&
            parsed > 0
        )
            ? parsed
            : fallback;
    }

    function nonNegativeInteger(
        value,
        fallback
    ) {

        const parsed =
            Number(
                value
            );

        return (
            Number.isInteger(
                parsed
            ) &&
            parsed >= 0
        )
            ? parsed
            : fallback;
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

    function cssEscape(
        value
    ) {

        if (
            window.CSS &&
            typeof window.CSS.escape ===
                'function'
        ) {

            return window.CSS.escape(
                String(value)
            );
        }

        return String(
            value
        ).replace(
            /["\\]/g,
            '\\$&'
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
     * LOADING
     * ============================================================ */

    function setLoading(
        loading
    ) {

        state.loading =
            loading;

        dom.prevPageBtn.disabled =
            loading ||
            state.currentPage <= 1;

        dom.nextPageBtn.disabled =
            loading ||
            state.currentPage >= state.lastPage;
    }

    function setButtonBusy(
        button,
        busy,
        busyText = ''
    ) {

        if (!button) {
            return;
        }

        if (busy) {

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

                const label =
                    document.createElement(
                        'span'
                    );

                label.textContent =
                    busyText;

                button.appendChild(
                    label
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
