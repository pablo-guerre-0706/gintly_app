'use strict';

/**
 * ================================================================
 * GINTLY
 * MOD-02 · CATÁLOGO Y DATOS MAESTROS
 * ================================================================
 *
 * Endpoints:
 *
 * GET  /api/v1/products
 * POST /api/v1/products
 *
 * GET  /api/v1/categories
 * GET  /api/v1/brands
 * GET  /api/v1/units
 *
 * POST /api/v1/logout
 *
 * Principios:
 *
 * - business_id nunca se envía desde frontend.
 * - user_id nunca se envía desde frontend.
 * - cantidades / dinero se mantienen como strings decimales.
 * - los IDs maestros se obtienen del tenant autenticado.
 * - type usa:
 *      simple
 *      compound
 *      service
 *
 * - is_taxable es boolean.
 * - service fuerza tracks_inventory = false.
 */

(() => {

    /* ============================================================
     * CONFIGURACIÓN
     * ============================================================ */

    const CONFIG_DEV = Boolean(
        window.CONFIG_DEV
    );

    const API_BASE_URL = String(
        window.GINTLY_API_BASE_URL ?? ''
    ).replace(/\/+$/, '');

    const API = Object.freeze({
        products:
            `${API_BASE_URL}/api/v1/products`,

        categories:
            `${API_BASE_URL}/api/v1/categories`,

        brands:
            `${API_BASE_URL}/api/v1/brands`,

        units:
            `${API_BASE_URL}/api/v1/units`,

        logout:
            `${API_BASE_URL}/api/v1/logout`
    });

    /* ============================================================
     * ESTADO
     * ============================================================ */

    const state = {
        products: [],
        categories: [],
        brands: [],
        units: [],

        categoryId: '',
        search: '',

        currentPage: 1,
        lastPage: 1,
        perPage: 25,
        total: 0,

        loading: false,

        requestController: null,

        productForm: {
            type: 'simple',
            isTaxable: false,
            categoryId: '',
            unitId: '',
            brandId: ''
        }
    };

    /* ============================================================
     * REFERENCIAS DOM
     * ============================================================ */

    const dom = {};

    /* ============================================================
     * MOCK DATA
     * ============================================================ */

    const MOCK_DATA = {
        categories: [
            {
                id: 1,
                name: 'Abarrotes',
                parent_id: null,
                is_active: true
            },
            {
                id: 2,
                name: 'Lácteos',
                parent_id: null,
                is_active: true
            },
            {
                id: 3,
                name: 'Bebidas',
                parent_id: null,
                is_active: true
            },
            {
                id: 4,
                name: 'Uso personal',
                parent_id: null,
                is_active: true
            },
            {
                id: 5,
                name: 'Panadería',
                parent_id: null,
                is_active: true
            },
            {
                id: 6,
                name: 'Herramienta',
                parent_id: null,
                is_active: true
            }
        ],

        brands: [
            {
                id: 1,
                name: 'Faisán',
                is_active: true
            },
            {
                id: 2,
                name: 'La Perfecta',
                is_active: true
            },
            {
                id: 3,
                name: 'Coca-Cola',
                is_active: true
            },
            {
                id: 4,
                name: 'Palmolive',
                is_active: true
            },
            {
                id: 5,
                name: 'Bimbo',
                is_active: true
            }
        ],

        units: [
            {
                id: 1,
                name: 'Libra',
                abbreviation: 'LB'
            },
            {
                id: 2,
                name: 'Litro',
                abbreviation: 'L'
            },
            {
                id: 3,
                name: 'Unidad',
                abbreviation: 'UND'
            },
            {
                id: 4,
                name: 'Kg',
                abbreviation: 'KG'
            },
            {
                id: 5,
                name: '500 ml',
                abbreviation: '500ML'
            },
            {
                id: 6,
                name: '250 ml',
                abbreviation: '250ML'
            }
        ],

        products: [
            {
                id: 1,
                sku: 'ARR-001',
                name: 'Arroz Faisán',
                category_id: 1,
                brand_id: 1,
                unit_id: 1,
                type: 'simple',
                sale_price: '24.00',
                cost: '18.00',
                tracks_inventory: true,
                is_taxable: true,
                is_active: true
            },
            {
                id: 2,
                sku: 'LAC-001',
                name: 'Leche Entera',
                category_id: 2,
                brand_id: 2,
                unit_id: 2,
                type: 'simple',
                sale_price: '42.00',
                cost: '33.50',
                tracks_inventory: true,
                is_taxable: true,
                is_active: true
            },
            {
                id: 3,
                sku: 'BEB-001',
                name: 'Coca-Cola 1.5L',
                category_id: 3,
                brand_id: 3,
                unit_id: 3,
                type: 'simple',
                sale_price: '54.00',
                cost: '42.00',
                tracks_inventory: true,
                is_taxable: true,
                is_active: true
            },
            {
                id: 4,
                sku: 'PER-001',
                name: 'Jabón Palmolive',
                category_id: 4,
                brand_id: 4,
                unit_id: 3,
                type: 'simple',
                sale_price: '38.00',
                cost: '27.00',
                tracks_inventory: true,
                is_taxable: true,
                is_active: true
            },
            {
                id: 5,
                sku: 'PAN-001',
                name: 'Pan Blanco',
                category_id: 5,
                brand_id: 5,
                unit_id: 3,
                type: 'simple',
                sale_price: '65.00',
                cost: '48.00',
                tracks_inventory: true,
                is_taxable: true,
                is_active: true
            },
            {
                id: 6,
                sku: 'LAC-002',
                name: 'Queso Fresco',
                category_id: 2,
                brand_id: 2,
                unit_id: 1,
                type: 'simple',
                sale_price: '92.00',
                cost: '74.00',
                tracks_inventory: true,
                is_taxable: false,
                is_active: true
            }
        ]
    };

    /* ============================================================
     * INICIALIZACIÓN
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

        await loadProducts();
    }

    /* ============================================================
     * CACHE DOM
     * ============================================================ */

    function cacheDom() {

        /* ---------------------------
         * Catálogo principal
         * ------------------------- */

        dom.tableBody =
            document.getElementById(
                'products-table-body'
            );

        dom.productCount =
            document.getElementById(
                'product-count'
            );

        dom.search =
            document.getElementById(
                'product-search'
            );

        dom.categoryFilters =
            document.getElementById(
                'category-filters'
            );

        dom.addProductBtn =
            document.getElementById(
                'add-product-btn'
            );

        dom.addCategoryBtn =
            document.getElementById(
                'add-category-btn'
            );

        dom.exportBtn =
            document.getElementById(
                'export-btn'
            );

        /* ---------------------------
         * Sidebar / layout
         * ------------------------- */

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

        dom.sidebar =
            document.getElementById(
                'app-sidebar'
            );

        dom.mobileSidebarBtn =
            document.getElementById(
                'mobile-sidebar-btn'
            );

        dom.sidebarCollapseBtn =
            document.getElementById(
                'sidebar-collapse-btn'
            );

        dom.sidebarOverlay =
            document.getElementById(
                'sidebar-overlay'
            );

        /* ---------------------------
         * Paginación
         * ------------------------- */

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

        /* ---------------------------
         * Modal
         * ------------------------- */

        dom.productModal =
            document.getElementById(
                'product-modal'
            );

        dom.productModalPanel =
            document.getElementById(
                'product-modal-panel'
            );

        dom.closeProductModalBtn =
            document.getElementById(
                'close-product-modal-btn'
            );

        dom.productForm =
            document.getElementById(
                'product-form'
            );

        dom.productFormGlobalError =
            document.getElementById(
                'product-form-global-error'
            );

        dom.productSku =
            document.getElementById(
                'product-sku'
            );

        dom.productName =
            document.getElementById(
                'product-name'
            );

        dom.productBrand =
            document.getElementById(
                'product-brand'
            );

        dom.productBrandOptions =
            document.getElementById(
                'product-brand-options'
            );

        dom.productCategoryId =
            document.getElementById(
                'product-category-id'
            );

        dom.productCategoryTrigger =
            document.getElementById(
                'product-category-trigger'
            );

        dom.productCategoryLabel =
            document.getElementById(
                'product-category-label'
            );

        dom.productCategoryMenu =
            document.getElementById(
                'product-category-menu'
            );

        dom.productUnitId =
            document.getElementById(
                'product-unit-id'
            );

        dom.productUnitTrigger =
            document.getElementById(
                'product-unit-trigger'
            );

        dom.productUnitLabel =
            document.getElementById(
                'product-unit-label'
            );

        dom.productUnitMenu =
            document.getElementById(
                'product-unit-menu'
            );

        dom.productTypeSelector =
            document.getElementById(
                'product-type-selector'
            );

        dom.productTaxSelector =
            document.getElementById(
                'product-tax-selector'
            );

        dom.productSalePrice =
            document.getElementById(
                'product-sale-price'
            );

        dom.productCost =
            document.getElementById(
                'product-cost'
            );

        dom.productSubmitBtn =
            document.getElementById(
                'product-submit-btn'
            );
    }
/* ============================================================
     * EVENTOS
     * ============================================================ */

    function bindEvents() {

        /* ---------------------------
         * Búsqueda
         * ------------------------- */

        dom.search.addEventListener(
            'input',
            debounce(
                async (event) => {
                    state.search =
                        String(
                            event.target.value ?? ''
                        ).trim();

                    state.currentPage = 1;

                    await loadProducts();
                },
                350
            )
        );

        /* ---------------------------
         * Filtro categoría
         * ------------------------- */

        dom.categoryFilters.addEventListener(
            'click',
            async (event) => {

                const button =
                    event.target.closest(
                        '[data-category-id]'
                    );

                if (!button) {
                    return;
                }

                const categoryId =
                    button.dataset.categoryId ?? '';

                if (
                    state.categoryId === categoryId
                ) {
                    return;
                }

                state.categoryId =
                    categoryId;

                state.currentPage = 1;

                setActiveCategoryChip(
                    button
                );

                await loadProducts();
            }
        );

        /* ---------------------------
         * Abrir modal
         * ------------------------- */

        dom.addProductBtn.addEventListener(
            'click',
            openProductModal
        );

        /* ---------------------------
         * Categoría futura
         * ------------------------- */

        dom.addCategoryBtn.addEventListener(
            'click',
            () => {
                console.log(
                    'Abrir formulario de categoría'
                );
            }
        );

        /* ---------------------------
         * Edición futura
         * ------------------------- */

        dom.tableBody.addEventListener(
            'click',
            (event) => {

                const button =
                    event.target.closest(
                        '[data-action="edit-product"]'
                    );

                if (!button) {
                    return;
                }

                const productId =
                    Number(
                        button.dataset.productId
                    );

                if (
                    !Number.isInteger(productId) ||
                    productId <= 0
                ) {
                    return;
                }

                console.log(
                    'Editar producto',
                    productId
                );
            }
        );

        /* ---------------------------
         * Exportar
         * ------------------------- */

        dom.exportBtn.addEventListener(
            'click',
            exportCurrentProducts
        );

        /* ---------------------------
         * Dark mode
         * ------------------------- */

        dom.darkModeToggle.addEventListener(
            'click',
            toggleTheme
        );

        /* ---------------------------
         * Logout
         * ------------------------- */

        dom.logoutBtn.addEventListener(
            'click',
            logout
        );

        /* ---------------------------
         * Paginación
         * ------------------------- */

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

                await loadProducts();
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

                await loadProducts();
            }
        );

        /* ---------------------------
         * Sidebar
         * ------------------------- */

        dom.mobileSidebarBtn.addEventListener(
            'click',
            openSidebar
        );

        dom.sidebarCollapseBtn.addEventListener(
            'click',
            () => {

                if (
                    window.innerWidth < 1280
                ) {
                    closeSidebar();
                }
            }
        );

        dom.sidebarOverlay.addEventListener(
            'click',
            closeSidebar
        );

        window.addEventListener(
            'resize',
            () => {

                if (
                    window.innerWidth >= 1280
                ) {
                    closeSidebarOverlayOnly();
                }
            }
        );

        /* ========================================================
         * EVENTOS DEL MODAL
         * ====================================================== */

        dom.closeProductModalBtn.addEventListener(
            'click',
            closeProductModal
        );

        dom.productModal.addEventListener(
            'click',
            (event) => {

                if (
                    event.target ===
                    dom.productModal
                ) {
                    closeProductModal();
                }
            }
        );

        /* ---------------------------
         * Dropdown categoría
         * ------------------------- */

        dom.productCategoryTrigger.addEventListener(
            'click',
            () => {
                toggleProductDropdown(
                    'category'
                );
            }
        );

        dom.productCategoryMenu.addEventListener(
            'click',
            (event) => {

                const option =
                    event.target.closest(
                        '[data-product-category-id]'
                    );

                if (!option) {
                    return;
                }

                selectProductCategory(
                    option.dataset
                        .productCategoryId,

                    option.dataset
                        .productCategoryName
                );
            }
        );

        /* ---------------------------
         * Dropdown unidad
         * ------------------------- */

        dom.productUnitTrigger.addEventListener(
            'click',
            () => {
                toggleProductDropdown(
                    'unit'
                );
            }
        );

        dom.productUnitMenu.addEventListener(
            'click',
            (event) => {

                const option =
                    event.target.closest(
                        '[data-product-unit-id]'
                    );

                if (!option) {
                    return;
                }

                selectProductUnit(
                    option.dataset
                        .productUnitId,

                    option.dataset
                        .productUnitName
                );
            }
        );

        /* ---------------------------
         * Tipo producto
         * ------------------------- */

        dom.productTypeSelector.addEventListener(
            'click',
            (event) => {

                const button =
                    event.target.closest(
                        '[data-product-type]'
                    );

                if (!button) {
                    return;
                }

                setProductType(
                    button.dataset.productType
                );
            }
        );

        /* ---------------------------
         * Impuesto
         * ------------------------- */

        dom.productTaxSelector.addEventListener(
            'click',
            (event) => {

                const button =
                    event.target.closest(
                        '[data-product-taxable]'
                    );

                if (!button) {
                    return;
                }

                setProductTaxable(
                    button.dataset
                        .productTaxable === 'true'
                );
            }
        );

        /* ---------------------------
         * Submit
         * ------------------------- */

        dom.productForm.addEventListener(
            'submit',
            submitProductForm
        );

        /* ---------------------------
         * Limpiar errores al escribir
         * ------------------------- */

        dom.productSku.addEventListener(
            'input',
            () => {
                clearProductFieldError(
                    'sku'
                );
            }
        );

        dom.productName.addEventListener(
            'input',
            () => {
                clearProductFieldError(
                    'name'
                );
            }
        );

        dom.productBrand.addEventListener(
            'input',
            () => {
                clearProductFieldError(
                    'brand_id'
                );
            }
        );

        dom.productSalePrice.addEventListener(
            'input',
            () => {
                clearProductFieldError(
                    'sale_price'
                );
            }
        );

        /* ---------------------------
         * Cerrar dropdown fuera
         * ------------------------- */

        document.addEventListener(
            'click',
            (event) => {

                const insideCategory =
                    dom.productCategoryTrigger
                        .contains(event.target) ||
                    dom.productCategoryMenu
                        .contains(event.target);

                const insideUnit =
                    dom.productUnitTrigger
                        .contains(event.target) ||
                    dom.productUnitMenu
                        .contains(event.target);

                if (!insideCategory) {
                    closeProductDropdown(
                        'category'
                    );
                }

                if (!insideUnit) {
                    closeProductDropdown(
                        'unit'
                    );
                }
            }
        );

        /* ---------------------------
         * ESC
         * ------------------------- */

        document.addEventListener(
            'keydown',
            (event) => {

                if (
                    event.key !== 'Escape'
                ) {
                    return;
                }

                if (
                    !dom.productCategoryMenu
                        .classList
                        .contains('hidden')
                ) {
                    closeProductDropdown(
                        'category'
                    );

                    return;
                }

                if (
                    !dom.productUnitMenu
                        .classList
                        .contains('hidden')
                ) {
                    closeProductDropdown(
                        'unit'
                    );

                    return;
                }

                if (
                    !dom.productModal
                        .classList
                        .contains('hidden')
                ) {
                    closeProductModal();
                }
            }
        );
    }

    /* ============================================================
     * DATOS MAESTROS
     * ============================================================ */

    async function loadMasterData() {

        try {

            if (CONFIG_DEV) {

                state.categories =
                    cloneArray(
                        MOCK_DATA.categories
                    );

                state.brands =
                    cloneArray(
                        MOCK_DATA.brands
                    );

                state.units =
                    cloneArray(
                        MOCK_DATA.units
                    );

                renderCategoryFilters();

                renderProductFormMasterData();

                return;
            }

            const [
                categoriesPayload,
                brandsPayload,
                unitsPayload
            ] = await Promise.all([
                apiFetch(
                    buildUrl(
                        API.categories,
                        {
                            per_page: 100,
                            is_active: 1
                        }
                    )
                ),

                apiFetch(
                    buildUrl(
                        API.brands,
                        {
                            per_page: 100,
                            is_active: 1
                        }
                    )
                ),

                apiFetch(
                    buildUrl(
                        API.units,
                        {
                            per_page: 100
                        }
                    )
                )
            ]);

            state.categories =
                unwrapCollection(
                    categoriesPayload
                );

            state.brands =
                unwrapCollection(
                    brandsPayload
                );

            state.units =
                unwrapCollection(
                    unitsPayload
                );

            renderCategoryFilters();

            renderProductFormMasterData();

        } catch (error) {

            console.error(
                'Error cargando datos maestros:',
                error
            );

            state.categories = [];
            state.brands = [];
            state.units = [];

            renderCategoryFilters();

            renderProductFormMasterData();
        }
    }

    /* ============================================================
     * CARGA PRODUCTOS
     * ============================================================ */

    async function loadProducts() {

        if (
            state.requestController
        ) {
            state.requestController.abort();
        }

        state.requestController =
            new AbortController();

        setLoading(true);

        renderLoadingRow();

        try {

            /* -----------------------
             * MOCK
             * --------------------- */

            if (CONFIG_DEV) {

                await delay(250);

                const mockResult =
                    filterMockProducts();

                state.products =
                    mockResult;

                state.total =
                    mockResult.length;

                state.currentPage = 1;
                state.lastPage = 1;

                renderProducts();

                renderPagination();

                return;
            }

            /* -----------------------
             * API REAL
             * --------------------- */

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
                state.categoryId !== ''
            ) {
                query.category_id =
                    state.categoryId;
            }

            const payload =
                await apiFetch(
                    buildUrl(
                        API.products,
                        query
                    ),
                    {
                        signal:
                            state.requestController
                                .signal
                    }
                );

            state.products =
                Array.isArray(payload?.data)
                    ? payload.data
                    : [];

            const meta =
                payload?.meta ?? {};

            state.currentPage =
                asPositiveInteger(
                    meta.current_page,
                    state.currentPage
                );

            state.lastPage =
                asPositiveInteger(
                    meta.last_page,
                    1
                );

            state.perPage =
                asPositiveInteger(
                    meta.per_page,
                    state.perPage
                );

            state.total =
                asNonNegativeInteger(
                    meta.total,
                    state.products.length
                );

            renderProducts();

            renderPagination();

        } catch (error) {

            if (
                error?.name ===
                'AbortError'
            ) {
                return;
            }

            console.error(
                'Error cargando productos:',
                error
            );

            state.products = [];
            state.total = 0;

            renderApiError(
                error
            );

            renderPagination();

        } finally {

            setLoading(false);
        }
    }
/* ============================================================
     * FETCH BASE
     * ============================================================ */

    async function apiFetch(
        url,
        options = {}
    ) {

        const token =
            getAccessToken();

        const headers =
            new Headers(
                options.headers ?? {}
            );

        headers.set(
            'Accept',
            'application/json'
        );

        headers.set(
            'X-Requested-With',
            'XMLHttpRequest'
        );

        if (token) {
            headers.set(
                'Authorization',
                `Bearer ${token}`
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

            error.status = 0;

            error.cause =
                networkError;

            throw error;
        }

        const payload =
            await parseJsonResponse(
                response
            );

        if (!response.ok) {

            const error =
                new Error(
                    resolveErrorMessage(
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
            response.headers
                .get('content-type') ?? '';

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

    function resolveErrorMessage(
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
                    'Tu sesión no es válida o ha expirado.'
                );

            case 403:
                return (
                    'No tienes autorización para realizar esta operación.'
                );

            case 404:
                return (
                    'El recurso solicitado no existe.'
                );

            case 409:
                return (
                    'La operación entra en conflicto con el estado actual del recurso.'
                );

            case 422:
                return (
                    'Los datos enviados no superaron la validación.'
                );

            case 429:
                return (
                    'Se realizaron demasiadas solicitudes. Intenta nuevamente en unos segundos.'
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

    /* ============================================================
     * RENDER TABLA
     * ============================================================ */

    function renderProducts() {

        dom.tableBody.replaceChildren();

        if (
            state.products.length === 0
        ) {

            renderEmptyRow();

            updateProductCount();

            refreshIcons();

            return;
        }

        const fragment =
            document.createDocumentFragment();

        for (
            const product
            of state.products
        ) {
            fragment.appendChild(
                createProductRow(
                    product
                )
            );
        }

        dom.tableBody.appendChild(
            fragment
        );

        updateProductCount();

        refreshIcons();
    }

    function createProductRow(
        product
    ) {

        const normalized =
            normalizeProduct(
                product
            );

        const row =
            document.createElement(
                'tr'
            );

        row.className =
            'table-row-hover h-[108px] border-b border-[#dddddd] ' +
            'bg-white text-[#252525] last:border-b-0 ' +
            'dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

        appendTextCell(
            row,
            normalized.sku,
            'px-[32px] text-[17px] font-bold text-black dark:text-white'
        );

        appendTextCell(
            row,
            normalized.name,
            'px-[18px] text-[14px]'
        );

        appendTextCell(
            row,
            normalized.categoryName,
            'px-[18px] text-[14px]'
        );

        appendTextCell(
            row,
            normalized.brandName,
            'px-[18px] text-[14px]'
        );

        appendTextCell(
            row,
            normalized.unitAbbreviation,
            'px-[18px] text-[14px]'
        );

        appendTextCell(
            row,
            formatCordobas(
                normalized.salePrice
            ),
            'px-[18px] text-[17px] font-semibold text-black dark:text-white'
        );

        appendTextCell(
            row,
            formatCordobas(
                normalized.cost
            ),
            'px-[18px] text-[17px] font-semibold text-black dark:text-white'
        );

        appendBadgeCell(
            row,
            productTypeLabel(
                normalized.type
            ),
            'border-[#6f75ff] bg-[#eef0ff] text-[#5865ff] ' +
            'dark:bg-indigo-950/40 dark:text-indigo-300'
        );

        appendBadgeCell(
            row,

            normalized.isTaxable
                ? 'IVA 15%'
                : 'Exento',

            normalized.isTaxable
                ? (
                    'border-[#ff8c38] bg-[#fff4e9] text-[#ff7b19] ' +
                    'dark:bg-orange-950/30 dark:text-orange-300'
                )
                : (
                    'border-slate-400 bg-slate-50 text-slate-600 ' +
                    'dark:bg-slate-800 dark:text-slate-300'
                )
        );

        appendBadgeCell(
            row,

            normalized.isActive
                ? 'Activo'
                : 'Inactivo',

            normalized.isActive
                ? (
                    'border-[#2ebf67] bg-[#e7f9ed] text-[#20a655] ' +
                    'dark:bg-emerald-950/30 dark:text-emerald-300'
                )
                : (
                    'border-[#b3b3b3] bg-[#f3f3f3] text-[#727272] ' +
                    'dark:bg-slate-800 dark:text-slate-300'
                )
        );

        const actionCell =
            document.createElement(
                'td'
            );

        actionCell.className =
            'px-[18px]';

        const editButton =
            document.createElement(
                'button'
            );

        editButton.type =
            'button';

        editButton.dataset.action =
            'edit-product';

        editButton.dataset.productId =
            String(
                normalized.id
            );

        editButton.className =
            'h-[42px] rounded-[6px] border border-gintly-primary ' +
            'bg-white px-[13px] text-[14px] font-medium text-[#19485a] ' +
            'transition hover:bg-cyan-50 dark:bg-slate-900 ' +
            'dark:text-cyan-300 dark:hover:bg-slate-800';

        editButton.textContent =
            'Editar';

        actionCell.appendChild(
            editButton
        );

        row.appendChild(
            actionCell
        );

        return row;
    }

    function normalizeProduct(
        product
    ) {

        const categoryId =
            firstDefined(
                product?.category_id,
                product?.category?.id
            );

        const brandId =
            firstDefined(
                product?.brand_id,
                product?.brand?.id
            );

        const unitId =
            firstDefined(
                product?.unit_id,
                product?.unit?.id
            );

        return {
            id:
                Number(
                    product?.id ?? 0
                ),

            sku:
                String(
                    product?.sku ?? ''
                ),

            name:
                String(
                    product?.name ?? ''
                ),

            categoryName:
                resolveRelatedName(
                    product?.category,
                    categoryId,
                    state.categories,
                    'Sin categoría'
                ),

            brandName:
                resolveRelatedName(
                    product?.brand,
                    brandId,
                    state.brands,
                    '—'
                ),

            unitAbbreviation:
                resolveUnitAbbreviation(
                    product?.unit,
                    unitId
                ),

            salePrice:
                String(
                    product?.sale_price ??
                    '0.00'
                ),

            cost:
                String(
                    product?.cost ??
                    '0.00'
                ),

            type:
                String(
                    product?.type ??
                    'simple'
                ),

            isTaxable:
                Boolean(
                    product?.is_taxable
                ),

            isActive:
                product?.is_active !== false
        };
    }

    function resolveRelatedName(
        embeddedRelation,
        id,
        collection,
        fallback
    ) {

        if (
            embeddedRelation &&
            typeof embeddedRelation.name ===
                'string'
        ) {
            return embeddedRelation.name;
        }

        const numericId =
            Number(id);

        if (
            Number.isInteger(
                numericId
            )
        ) {

            const item =
                collection.find(
                    (entry) =>
                        Number(entry.id) ===
                        numericId
                );

            if (
                item &&
                typeof item.name ===
                    'string'
            ) {
                return item.name;
            }
        }

        return fallback;
    }

    function resolveUnitAbbreviation(
        embeddedRelation,
        unitId
    ) {

        if (
            embeddedRelation &&
            typeof embeddedRelation
                .abbreviation === 'string'
        ) {
            return (
                embeddedRelation
                    .abbreviation
            );
        }

        const numericUnitId =
            Number(unitId);

        const unit =
            state.units.find(
                (entry) =>
                    Number(entry.id) ===
                    numericUnitId
            );

        if (
            unit &&
            typeof unit.abbreviation ===
                'string'
        ) {
            return unit.abbreviation;
        }

        return '—';
    }

    function appendTextCell(
        row,
        value,
        className
    ) {

        const cell =
            document.createElement(
                'td'
            );

        cell.className =
            className;

        cell.textContent =
            value;

        row.appendChild(
            cell
        );
    }

    function appendBadgeCell(
        row,
        text,
        colorClasses
    ) {

        const cell =
            document.createElement(
                'td'
            );

        cell.className =
            'px-[18px]';

        const badge =
            document.createElement(
                'span'
            );

        badge.className =
            'inline-flex min-h-[42px] items-center rounded-[6px] ' +
            'border px-[10px] text-[14px] font-medium ' +
            colorClasses;

        badge.textContent =
            text;

        cell.appendChild(
            badge
        );

        row.appendChild(
            cell
        );
    }

    /* ============================================================
     * FILAS ESPECIALES
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

        cell.colSpan = 11;

        cell.className =
            'h-[170px] px-8 text-center text-slate-500 dark:text-slate-400';

        const wrapper =
            document.createElement(
                'div'
            );

        wrapper.className =
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
            'Cargando productos...';

        wrapper.append(
            spinner,
            text
        );

        cell.appendChild(
            wrapper
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

        cell.colSpan = 11;

        cell.className =
            'h-[190px] px-8 text-center text-slate-500 dark:text-slate-400';

        const wrapper =
            document.createElement(
                'div'
            );

        wrapper.className =
            'flex flex-col items-center justify-center gap-3';

        const icon =
            document.createElement(
                'i'
            );

        icon.dataset.lucide =
            'package-search';

        icon.className =
            'h-8 w-8';

        const title =
            document.createElement(
                'strong'
            );

        title.className =
            'text-base font-semibold text-slate-700 dark:text-slate-200';

        title.textContent =
            'No se encontraron productos';

        const detail =
            document.createElement(
                'span'
            );

        detail.className =
            'text-sm';

        detail.textContent =
            state.search ||
            state.categoryId

                ? (
                    'Modifica la búsqueda o selecciona otra categoría.'
                )

                : (
                    'Todavía no hay productos registrados.'
                );

        wrapper.append(
            icon,
            title,
            detail
        );

        cell.appendChild(
            wrapper
        );

        row.appendChild(
            cell
        );

        dom.tableBody.appendChild(
            row
        );
    }

    function renderApiError(
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

        cell.colSpan = 11;

        cell.className =
            'border-y border-red-200 bg-red-50 px-8 py-8 text-red-800 ' +
            'dark:border-red-900 dark:bg-red-950/40 dark:text-red-300';

        const wrapper =
            document.createElement(
                'div'
            );

        wrapper.className =
            'flex items-start justify-center gap-3';

        const icon =
            document.createElement(
                'i'
            );

        icon.dataset.lucide =
            'circle-alert';

        icon.className =
            'mt-[1px] h-5 w-5 shrink-0';

        const content =
            document.createElement(
                'div'
            );

        const title =
            document.createElement(
                'strong'
            );

        title.className =
            'block font-semibold';

        if (
            error?.status === 403
        ) {
            title.textContent =
                'Acceso denegado';

        } else if (
            error?.status === 500
        ) {
            title.textContent =
                'Error interno del servidor';

        } else if (
            error?.status === 401
        ) {
            title.textContent =
                'Sesión no válida';

        } else {
            title.textContent =
                'No fue posible cargar el catálogo';
        }

        const detail =
            document.createElement(
                'p'
            );

        detail.className =
            'mt-1 text-sm';

        detail.textContent =
            error?.message ||
            'Ocurrió un error inesperado al consultar los productos.';

        content.append(
            title,
            detail
        );

        wrapper.append(
            icon,
            content
        );

        cell.appendChild(
            wrapper
        );

        row.appendChild(
            cell
        );

        dom.tableBody.appendChild(
            row
        );

        updateProductCount();

        refreshIcons();
    }

    function updateProductCount() {

        const total =
            state.total;

        dom.productCount.textContent =
            `${total} ${
                total === 1
                    ? 'producto'
                    : 'productos'
            }`;
    }
/* ============================================================
     * CATEGORÍAS · FILTRO SUPERIOR
     * ============================================================ */

    function renderCategoryFilters() {

        dom.categoryFilters.replaceChildren();

        const allButton =
            createCategoryButton({
                id: '',
                name: 'Todas'
            });

        dom.categoryFilters.appendChild(
            allButton
        );

        const activeCategories =
            state.categories
                .filter(
                    (category) =>
                        category?.is_active !==
                        false
                )
                .slice(
                    0,
                    10
                );

        for (
            const category
            of activeCategories
        ) {

            dom.categoryFilters.appendChild(
                createCategoryButton(
                    category
                )
            );
        }

        refreshIcons();
    }

    function createCategoryButton(
        category
    ) {

        const button =
            document.createElement(
                'button'
            );

        const id =
            category.id === ''
                ? ''
                : String(
                    category.id
                );

        const active =
            state.categoryId === id;

        button.type =
            'button';

        button.dataset.categoryId =
            id;

        button.setAttribute(
            'aria-pressed',
            active
                ? 'true'
                : 'false'
        );

        button.className =
            'category-chip h-[54px] shrink-0 rounded-[15px] border ' +
            'border-[#bebebe] bg-[#f5f5f5] px-[22px] text-[16px] ' +
            'font-medium text-[#686868] hover:border-gintly-primary ' +
            'hover:text-gintly-primary';

        button.textContent =
            String(
                category.name ?? ''
            );

        return button;
    }

    function setActiveCategoryChip(
        activeButton
    ) {

        const buttons =
            dom.categoryFilters
                .querySelectorAll(
                    '[data-category-id]'
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
     * PAGINACIÓN
     * ============================================================ */

    function renderPagination() {

        const hasMultiplePages =
            state.lastPage > 1;

        dom.paginationContainer
            .classList
            .toggle(
                'hidden',
                !hasMultiplePages
            );

        dom.paginationContainer
            .classList
            .toggle(
                'flex',
                hasMultiplePages
            );

        if (!hasMultiplePages) {
            return;
        }

        dom.paginationLabel.textContent =
            `Página ${
                state.currentPage
            } de ${
                state.lastPage
            }`;

        dom.prevPageBtn.disabled =
            state.loading ||
            state.currentPage <= 1;

        dom.nextPageBtn.disabled =
            state.loading ||
            state.currentPage >=
                state.lastPage;
    }

    /* ============================================================
     * MOCK FILTER
     * ============================================================ */

    function filterMockProducts() {

        const normalizedSearch =
            normalizeSearchText(
                state.search
            );

        return MOCK_DATA.products.filter(
            (product) => {

                const matchesCategory =
                    state.categoryId === '' ||
                    String(
                        product.category_id
                    ) ===
                    state.categoryId;

                if (!matchesCategory) {
                    return false;
                }

                if (
                    normalizedSearch === ''
                ) {
                    return true;
                }

                const haystack =
                    normalizeSearchText(
                        `${product.sku} ${product.name}`
                    );

                return haystack.includes(
                    normalizedSearch
                );
            }
        );
    }

    /* ============================================================
     * MODAL · DATOS MAESTROS
     * ============================================================ */

    function renderProductFormMasterData() {

        renderProductCategoryMenu();

        renderProductUnitMenu();

        renderProductBrandOptions();
    }

    function renderProductCategoryMenu() {

        dom.productCategoryMenu
            .replaceChildren();

        const fragment =
            document.createDocumentFragment();

        const categories =
            state.categories.filter(
                (category) =>
                    category?.is_active !==
                    false
            );

        for (
            const category
            of categories
        ) {

            const button =
                document.createElement(
                    'button'
                );

            button.type =
                'button';

            button.dataset.productCategoryId =
                String(
                    category.id
                );

            button.dataset.productCategoryName =
                String(
                    category.name ?? ''
                );

            button.className =
                'block min-h-[66px] w-full border-b border-[#d1d1d1] ' +
                'px-[17px] text-left text-[17px] text-[#6a6a6a] ' +
                'transition last:border-b-0 hover:bg-[#f5f5f5] ' +
                'dark:border-slate-700 dark:text-slate-300 ' +
                'dark:hover:bg-slate-800';

            button.textContent =
                String(
                    category.name ?? ''
                );

            fragment.appendChild(
                button
            );
        }

        if (
            categories.length === 0
        ) {

            const empty =
                document.createElement(
                    'div'
                );

            empty.className =
                'px-4 py-5 text-sm text-slate-500';

            empty.textContent =
                'No existen categorías disponibles.';

            fragment.appendChild(
                empty
            );
        }

        dom.productCategoryMenu
            .appendChild(
                fragment
            );
    }

    function renderProductUnitMenu() {

        dom.productUnitMenu
            .replaceChildren();

        const fragment =
            document.createDocumentFragment();

        for (
            const unit
            of state.units
        ) {

            const button =
                document.createElement(
                    'button'
                );

            button.type =
                'button';

            button.dataset.productUnitId =
                String(
                    unit.id
                );

            button.dataset.productUnitName =
                String(
                    unit.name ??
                    unit.abbreviation ??
                    ''
                );

            button.className =
                'block min-h-[66px] w-full border-b border-[#d1d1d1] ' +
                'px-[17px] text-left text-[17px] text-[#6a6a6a] ' +
                'transition last:border-b-0 hover:bg-[#f5f5f5] ' +
                'dark:border-slate-700 dark:text-slate-300 ' +
                'dark:hover:bg-slate-800';

            button.textContent =
                String(
                    unit.name ??
                    unit.abbreviation ??
                    ''
                );

            fragment.appendChild(
                button
            );
        }

        if (
            state.units.length === 0
        ) {

            const empty =
                document.createElement(
                    'div'
                );

            empty.className =
                'px-4 py-5 text-sm text-slate-500';

            empty.textContent =
                'No existen unidades de medida disponibles.';

            fragment.appendChild(
                empty
            );
        }

        dom.productUnitMenu
            .appendChild(
                fragment
            );
    }

    function renderProductBrandOptions() {

        dom.productBrandOptions
            .replaceChildren();

        const fragment =
            document.createDocumentFragment();

        for (
            const brand
            of state.brands
        ) {

            if (
                brand?.is_active === false
            ) {
                continue;
            }

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                String(
                    brand.name ?? ''
                );

            fragment.appendChild(
                option
            );
        }

        dom.productBrandOptions
            .appendChild(
                fragment
            );
    }

    /* ============================================================
     * MODAL · ABRIR / CERRAR
     * ============================================================ */

    function openProductModal() {

        resetProductForm();

        dom.productModal
            .classList
            .remove(
                'hidden'
            );

        document.body
            .classList
            .add(
                'overflow-hidden'
            );

        requestAnimationFrame(
            () => {

                dom.productModal
                    .classList
                    .remove(
                        'opacity-0'
                    );

                dom.productModalPanel
                    .classList
                    .remove(
                        'translate-y-3',
                        'scale-[0.985]'
                    );

                dom.productModalPanel
                    .classList
                    .add(
                        'translate-y-0',
                        'scale-100'
                    );
            }
        );

        window.setTimeout(
            () => {

                dom.productSku.focus();

            },
            210
        );
    }

    function closeProductModal() {

        closeProductDropdown(
            'category'
        );

        closeProductDropdown(
            'unit'
        );

        dom.productModal
            .classList
            .add(
                'opacity-0'
            );

        dom.productModalPanel
            .classList
            .remove(
                'translate-y-0',
                'scale-100'
            );

        dom.productModalPanel
            .classList
            .add(
                'translate-y-3',
                'scale-[0.985]'
            );

        window.setTimeout(
            () => {

                dom.productModal
                    .classList
                    .add(
                        'hidden'
                    );

                document.body
                    .classList
                    .remove(
                        'overflow-hidden'
                    );

            },
            200
        );
    }

    /* ============================================================
     * MODAL · DROPDOWNS
     * ============================================================ */

    function toggleProductDropdown(
        type
    ) {

        if (
            type === 'category'
        ) {

            closeProductDropdown(
                'unit'
            );

            const opening =
                dom.productCategoryMenu
                    .classList
                    .contains(
                        'hidden'
                    );

            dom.productCategoryMenu
                .classList
                .toggle(
                    'hidden',
                    !opening
                );

            dom.productCategoryTrigger
                .setAttribute(
                    'aria-expanded',
                    opening
                        ? 'true'
                        : 'false'
                );

            return;
        }

        closeProductDropdown(
            'category'
        );

        const opening =
            dom.productUnitMenu
                .classList
                .contains(
                    'hidden'
                );

        dom.productUnitMenu
            .classList
            .toggle(
                'hidden',
                !opening
            );

        dom.productUnitTrigger
            .setAttribute(
                'aria-expanded',
                opening
                    ? 'true'
                    : 'false'
            );
    }

    function closeProductDropdown(
        type
    ) {

        if (
            type === 'category'
        ) {

            dom.productCategoryMenu
                .classList
                .add(
                    'hidden'
                );

            dom.productCategoryTrigger
                .setAttribute(
                    'aria-expanded',
                    'false'
                );

            return;
        }

        dom.productUnitMenu
            .classList
            .add(
                'hidden'
            );

        dom.productUnitTrigger
            .setAttribute(
                'aria-expanded',
                'false'
            );
    }

    function selectProductCategory(
        categoryId,
        categoryName
    ) {

        state.productForm.categoryId =
            String(
                categoryId
            );

        dom.productCategoryId.value =
            String(
                categoryId
            );

        dom.productCategoryLabel
            .textContent =
                String(
                    categoryName
                );

        dom.productCategoryTrigger
            .classList
            .remove(
                'text-[#696969]'
            );

        dom.productCategoryTrigger
            .classList
            .add(
                'text-[#353535]'
            );

        clearProductFieldError(
            'category_id'
        );

        closeProductDropdown(
            'category'
        );
    }

    function selectProductUnit(
        unitId,
        unitName
    ) {

        state.productForm.unitId =
            String(
                unitId
            );

        dom.productUnitId.value =
            String(
                unitId
            );

        dom.productUnitLabel
            .textContent =
                String(
                    unitName
                );

        dom.productUnitTrigger
            .classList
            .remove(
                'text-[#777777]'
            );

        dom.productUnitTrigger
            .classList
            .add(
                'text-[#353535]'
            );

        clearProductFieldError(
            'unit_id'
        );

        closeProductDropdown(
            'unit'
        );
    }

    /* ============================================================
     * MODAL · SELECTORES TIPO / IMPUESTO
     * ============================================================ */

    function setProductType(
        type
    ) {

        const allowed = [
            'simple',
            'compound',
            'service'
        ];

        if (
            !allowed.includes(
                type
            )
        ) {
            return;
        }

        state.productForm.type =
            type;

        const buttons =
            dom.productTypeSelector
                .querySelectorAll(
                    '[data-product-type]'
                );

        for (
            const button
            of buttons
        ) {

            setSelectorVisualState(
                button,

                button.dataset
                    .productType ===
                    type
            );
        }

        clearProductFieldError(
            'type'
        );
    }

    function setProductTaxable(
        isTaxable
    ) {

        state.productForm.isTaxable =
            Boolean(
                isTaxable
            );

        const buttons =
            dom.productTaxSelector
                .querySelectorAll(
                    '[data-product-taxable]'
                );

        for (
            const button
            of buttons
        ) {

            const buttonValue =
                button.dataset
                    .productTaxable ===
                    'true';

            setSelectorVisualState(
                button,

                buttonValue ===
                state.productForm
                    .isTaxable
            );
        }

        clearProductFieldError(
            'is_taxable'
        );
    }

    function setSelectorVisualState(
        button,
        active
    ) {

        button.classList.toggle(
            'bg-gintly-primary',
            active
        );

        button.classList.toggle(
            'text-white',
            active
        );

        button.classList.toggle(
            'font-semibold',
            active
        );

        button.classList.toggle(
            'bg-[#f1f1f1]',
            !active
        );

        button.classList.toggle(
            'text-[#696969]',
            !active
        );

        button.classList.toggle(
            'font-medium',
            !active
        );
    }
/* ============================================================
     * MODAL · SUBMIT
     * ============================================================ */

    async function submitProductForm(
        event
    ) {

        event.preventDefault();

        clearProductFormErrors();

        const brandId =
            resolveProductBrandId(
                dom.productBrand.value
            );

        state.productForm.brandId =
            brandId === null
                ? ''
                : String(
                    brandId
                );

        const payload = {
            sku:
                dom.productSku.value
                    .trim()
                    .toUpperCase(),

            name:
                dom.productName.value
                    .trim(),

            type:
                state.productForm.type,

            category_id:
                state.productForm
                    .categoryId
                    ? Number(
                        state.productForm
                            .categoryId
                    )
                    : null,

            brand_id:
                state.productForm
                    .brandId
                    ? Number(
                        state.productForm
                            .brandId
                    )
                    : null,

            unit_id:
                state.productForm
                    .unitId
                    ? Number(
                        state.productForm
                            .unitId
                    )
                    : null,

            sale_price:
                normalizeMoneyInput(
                    dom.productSalePrice
                        .value
                ),

            cost:
                '0.00',

            tracks_inventory:
                state.productForm.type !==
                'service',

            is_taxable:
                state.productForm
                    .isTaxable,

            is_active:
                true
        };

        if (
            !validateProductPayload(
                payload
            )
        ) {
            return;
        }

        setButtonBusy(
            dom.productSubmitBtn,
            true,
            'Registrando...'
        );

        try {

            /* ====================================================
             * MOCK
             * ================================================= */

            if (CONFIG_DEV) {

                await delay(350);

                const createdProduct =
                    createMockProduct(
                        payload
                    );

                MOCK_DATA.products.push(
                    createdProduct
                );

                window.alert(
                    'Producto registrado correctamente.'
                );

                closeProductModal();

                resetCatalogFilters();

                await loadProducts();

                return;
            }

            /* ====================================================
             * API REAL
             * ================================================= */

            const createdPayload =
                await apiFetch(
                    API.products,
                    {
                        method: 'POST',

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

            console.log(
                'Producto creado:',
                createdPayload
            );

            window.alert(
                'Producto registrado correctamente.'
            );

            closeProductModal();

            resetCatalogFilters();

            await loadProducts();

        } catch (error) {

            console.error(
                'Error registrando producto:',
                error
            );

            handleProductFormApiError(
                error
            );

        } finally {

            setButtonBusy(
                dom.productSubmitBtn,
                false
            );
        }
    }

    /* ============================================================
     * VALIDACIÓN FORMULARIO
     * ============================================================ */

    function validateProductPayload(
        payload
    ) {

        let valid = true;

        if (!payload.sku) {

            setProductFieldError(
                'sku',
                'El SKU / código es obligatorio.'
            );

            valid = false;
        }

        if (!payload.name) {

            setProductFieldError(
                'name',
                'El nombre del producto es obligatorio.'
            );

            valid = false;
        }

        /*
         * El backend permite brand_id nullable,
         * pero este Figma exige Marca como campo visual obligatorio.
         */
        if (!payload.brand_id) {

            setProductFieldError(
                'brand_id',
                'Selecciona una marca existente.'
            );

            valid = false;
        }

        if (!payload.category_id) {

            setProductFieldError(
                'category_id',
                'Selecciona una categoría.'
            );

            valid = false;
        }

        if (!payload.unit_id) {

            setProductFieldError(
                'unit_id',
                'Selecciona una unidad de medida.'
            );

            valid = false;
        }

        if (!payload.type) {

            setProductFieldError(
                'type',
                'Selecciona el tipo de producto.'
            );

            valid = false;
        }

        if (
            payload.sale_price === '' ||
            !isValidMoneyString(
                payload.sale_price
            )
        ) {

            setProductFieldError(
                'sale_price',
                'Ingresa un precio válido mayor o igual a C$ 0.00.'
            );

            valid = false;
        }

        return valid;
    }

    function resolveProductBrandId(
        value
    ) {

        const normalized =
            normalizeSearchText(
                value
            );

        if (!normalized) {
            return null;
        }

        const brand =
            state.brands.find(
                (item) => {

                    return (
                        normalizeSearchText(
                            item?.name
                        ) ===
                        normalized
                    );
                }
            );

        return brand
            ? Number(
                brand.id
            )
            : null;
    }

    function normalizeMoneyInput(
        value
    ) {

        return String(
            value ?? ''
        )
            .trim()
            .replace(
                /^C\$\s*/i,
                ''
            )
            .replace(
                /,/g,
                ''
            );
    }

    function isValidMoneyString(
        value
    ) {

        return (
            /^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/
                .test(
                    String(value)
                )
        );
    }

    /* ============================================================
     * MOCK · CREAR PRODUCTO
     * ============================================================ */

    function createMockProduct(
        payload
    ) {

        const highestId =
            MOCK_DATA.products.reduce(
                (
                    highest,
                    product
                ) => {

                    return Math.max(
                        highest,
                        Number(
                            product.id
                        ) || 0
                    );
                },
                0
            );

        return {
            id:
                highestId + 1,

            sku:
                payload.sku,

            name:
                payload.name,

            category_id:
                payload.category_id,

            brand_id:
                payload.brand_id,

            unit_id:
                payload.unit_id,

            type:
                payload.type,

            sale_price:
                formatDecimalForApiMock(
                    payload.sale_price,
                    2
                ),

            cost:
                '0.00',

            tracks_inventory:
                payload.tracks_inventory,

            is_taxable:
                payload.is_taxable,

            is_active:
                true
        };
    }

    function formatDecimalForApiMock(
        value,
        scale
    ) {

        const text =
            String(
                value ?? '0'
            );

        const [
            integerPart,
            decimalPart = ''
        ] = text.split('.');

        return (
            `${integerPart}.` +
            decimalPart
                .slice(
                    0,
                    scale
                )
                .padEnd(
                    scale,
                    '0'
                )
        );
    }

    /* ============================================================
     * ERRORES FORMULARIO DESDE API
     * ============================================================ */

    function handleProductFormApiError(
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

                setProductFieldError(
                    field,
                    message
                );
            }

            showProductFormGlobalError(
                'Revisa los campos marcados antes de continuar.'
            );

            return;
        }

        if (
            error?.status === 401
        ) {

            showProductFormGlobalError(
                'Tu sesión ha expirado. Inicia sesión nuevamente.'
            );

            return;
        }

        if (
            error?.status === 403
        ) {

            showProductFormGlobalError(
                'No tienes permisos para registrar productos.'
            );

            return;
        }

        if (
            error?.status === 409
        ) {

            showProductFormGlobalError(
                error?.message ||
                'Existe un conflicto con los datos del producto.'
            );

            return;
        }

        if (
            error?.status === 500
        ) {

            showProductFormGlobalError(
                'El servidor no pudo registrar el producto. Intenta nuevamente.'
            );

            return;
        }

        showProductFormGlobalError(
            error?.message ||
            'No fue posible registrar el producto.'
        );
    }

    /* ============================================================
     * ERRORES DE CAMPO
     * ============================================================ */

    function setProductFieldError(
        field,
        message
    ) {

        const validation =
            document.querySelector(
                `[data-validation-for="${cssEscape(field)}"]`
            );

        if (validation) {

            validation.classList.remove(
                'bg-[#f2f2f2]',
                'text-[#6c6c6c]'
            );

            validation.classList.add(
                'bg-red-50',
                'text-red-700'
            );

            const text =
                validation.querySelector(
                    'span'
                );

            if (text) {
                text.textContent =
                    message;
            }
        }

        const control =
            getProductFieldControl(
                field
            );

        if (control) {

            control.classList.remove(
                'border-[#bdbdbd]'
            );

            control.classList.add(
                'border-red-500',
                'ring-1',
                'ring-red-200'
            );
        }
    }

    function clearProductFieldError(
        field
    ) {

        const validation =
            document.querySelector(
                `[data-validation-for="${cssEscape(field)}"]`
            );

        if (validation) {

            validation.classList.remove(
                'bg-red-50',
                'text-red-700'
            );

            validation.classList.add(
                'bg-[#f2f2f2]',
                'text-[#6c6c6c]'
            );

            const text =
                validation.querySelector(
                    'span'
                );

            if (text) {

                text.textContent =
                    'Es de carácter obligatorio';
            }
        }

        const control =
            getProductFieldControl(
                field
            );

        if (control) {

            control.classList.remove(
                'border-red-500',
                'ring-1',
                'ring-red-200'
            );

            control.classList.add(
                'border-[#bdbdbd]'
            );
        }
    }

    function getProductFieldControl(
        field
    ) {

        switch (field) {

            case 'sku':
                return dom.productSku;

            case 'name':
                return dom.productName;

            case 'brand_id':
                return dom.productBrand;

            case 'category_id':
                return dom.productCategoryTrigger;

            case 'unit_id':
                return dom.productUnitTrigger;

            case 'sale_price':
                return dom.productSalePrice;

            default:
                return null;
        }
    }

    function clearProductFormErrors() {

        const fields = [
            'sku',
            'name',
            'brand_id',
            'category_id',
            'unit_id',
            'type',
            'is_taxable',
            'sale_price'
        ];

        for (
            const field
            of fields
        ) {

            clearProductFieldError(
                field
            );
        }

        dom.productFormGlobalError
            .classList
            .add(
                'hidden'
            );

        dom.productFormGlobalError
            .textContent = '';
    }

    function showProductFormGlobalError(
        message
    ) {

        dom.productFormGlobalError
            .textContent =
                message;

        dom.productFormGlobalError
            .classList
            .remove(
                'hidden'
            );
    }

    /* ============================================================
     * RESET MODAL
     * ============================================================ */

    function resetProductForm() {

        dom.productForm.reset();

        state.productForm.type =
            'simple';

        state.productForm.isTaxable =
            false;

        state.productForm.categoryId =
            '';

        state.productForm.unitId =
            '';

        state.productForm.brandId =
            '';

        dom.productCategoryId.value =
            '';

        dom.productUnitId.value =
            '';

        dom.productCategoryLabel
            .textContent =
                'Seleccione una categoría';

        dom.productUnitLabel
            .textContent =
                'Ejemplo: Litro';

        dom.productCategoryTrigger
            .classList
            .remove(
                'text-[#353535]'
            );

        dom.productCategoryTrigger
            .classList
            .add(
                'text-[#696969]'
            );

        dom.productUnitTrigger
            .classList
            .remove(
                'text-[#353535]'
            );

        dom.productUnitTrigger
            .classList
            .add(
                'text-[#777777]'
            );

        dom.productCost.value =
            '0.00';

        setProductType(
            'simple'
        );

        setProductTaxable(
            false
        );

        clearProductFormErrors();

        closeProductDropdown(
            'category'
        );

        closeProductDropdown(
            'unit'
        );
    }

    function resetCatalogFilters() {

        state.categoryId =
            '';

        state.search =
            '';

        state.currentPage =
            1;

        dom.search.value =
            '';

        const allCategoryButton =
            dom.categoryFilters
                .querySelector(
                    '[data-category-id=""]'
                );

        if (
            allCategoryButton
        ) {

            setActiveCategoryChip(
                allCategoryButton
            );

            state.categoryId = '';
        }
    }

    /* ============================================================
     * EXPORTACIÓN
     * ============================================================ */

    function exportCurrentProducts() {

        if (
            state.products.length === 0
        ) {
            return;
        }

        setButtonBusy(
            dom.exportBtn,
            true,
            'Exportando...'
        );

        try {

            const headers = [
                'SKU',
                'Producto',
                'Categoría',
                'Marca',
                'Unidad',
                'Precio de venta',
                'Costo',
                'Tipo',
                'Impuestos',
                'Estado'
            ];

            const rows =
                state.products.map(
                    (product) => {

                        const item =
                            normalizeProduct(
                                product
                            );

                        return [
                            item.sku,
                            item.name,
                            item.categoryName,
                            item.brandName,
                            item.unitAbbreviation,
                            item.salePrice,
                            item.cost,
                            productTypeLabel(
                                item.type
                            ),
                            item.isTaxable
                                ? 'IVA 15%'
                                : 'Exento',
                            item.isActive
                                ? 'Activo'
                                : 'Inactivo'
                        ];
                    }
                );

            const csv =
                [
                    headers,
                    ...rows
                ]
                    .map(
                        (row) => {

                            return row
                                .map(
                                    csvEscape
                                )
                                .join(',');
                        }
                    )
                    .join('\r\n');

            /*
             * BOM UTF-8:
             * permite que Excel reconozca correctamente
             * tildes y caracteres españoles.
             */
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
                createExportFilename();

            document.body
                .appendChild(
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

    function createExportFilename() {

        const now =
            new Date();

        const year =
            now.getFullYear();

        const month =
            String(
                now.getMonth() + 1
            ).padStart(
                2,
                '0'
            );

        const day =
            String(
                now.getDate()
            ).padStart(
                2,
                '0'
            );

        return (
            `gintly-catalogo-${year}-${month}-${day}.csv`
        );
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
                `"${text.replaceAll(
                    '"',
                    '""'
                )}"`
            );
        }

        return text;
    }
/* ============================================================
     * LOGOUT
     * ============================================================ */

    async function logout() {

        setButtonBusy(
            dom.logoutBtn,
            true,
            'Cerrando sesión...'
        );

        try {

            if (!CONFIG_DEV) {

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
                'No fue posible cerrar la sesión en el servidor:',
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

        const storedTheme =
            localStorage.getItem(
                'gintly_theme'
            );

        const dark =
            storedTheme ===
            'dark';

        applyTheme(
            dark
        );
    }

    function toggleTheme() {

        const isCurrentlyDark =
            document.documentElement
                .classList
                .contains(
                    'dark'
                );

        const nextDarkState =
            !isCurrentlyDark;

        applyTheme(
            nextDarkState
        );

        localStorage.setItem(
            'gintly_theme',

            nextDarkState
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

        dom.darkModeToggle
            .setAttribute(
                'aria-checked',
                dark
                    ? 'true'
                    : 'false'
            );

        dom.darkModeToggle
            .classList
            .toggle(
                'bg-gintly-primary',
                dark
            );

        dom.darkModeKnob
            .style
            .transform =
                dark
                    ? 'translateX(20px)'
                    : 'translateX(0)';
    }

    /* ============================================================
     * SIDEBAR
     * ============================================================ */

    function openSidebar() {

        dom.sidebar.dataset.open =
            'true';

        dom.sidebarOverlay
            .classList
            .remove(
                'hidden'
            );

        document.body
            .classList
            .add(
                'overflow-hidden'
            );
    }

    function closeSidebar() {

        dom.sidebar.dataset.open =
            'false';

        dom.sidebarOverlay
            .classList
            .add(
                'hidden'
            );

        document.body
            .classList
            .remove(
                'overflow-hidden'
            );
    }

    function closeSidebarOverlayOnly() {

        dom.sidebar.dataset.open =
            'false';

        dom.sidebarOverlay
            .classList
            .add(
                'hidden'
            );

        document.body
            .classList
            .remove(
                'overflow-hidden'
            );
    }

    /* ============================================================
     * UTILIDADES DE COLECCIONES
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

    function cloneArray(
        source
    ) {

        return source.map(
            (item) => ({
                ...item
            })
        );
    }

    /* ============================================================
     * URL
     * ============================================================ */

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

    /* ============================================================
     * FORMATO DINERO
     * ============================================================ */

    function formatCordobas(
        decimalString
    ) {

        const normalized =
            normalizeDecimalForDisplay(
                decimalString,
                2
            );

        return (
            `C$ ${normalized}`
        );
    }

    function normalizeDecimalForDisplay(
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
            return text;
        }

        const sign =
            match[1];

        const integerPart =
            match[2];

        const decimalPart =
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

        const groupedInteger =
            integerPart.replace(
                /\B(?=(\d{3})+(?!\d))/g,
                ','
            );

        return scale > 0
            ? (
                `${sign}${groupedInteger}.${decimalPart}`
            )
            : (
                `${sign}${groupedInteger}`
            );
    }

    /* ============================================================
     * LABEL PRODUCT TYPE
     * ============================================================ */

    function productTypeLabel(
        type
    ) {

        switch (type) {

            case 'simple':
                return 'Simple';

            case 'compound':
                return 'Compuesto';

            case 'service':
                return 'Servicio';

            default:
                return type || '—';
        }
    }

    /* ============================================================
     * NORMALIZACIÓN TEXTO
     * ============================================================ */

    function normalizeSearchText(
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

    /* ============================================================
     * DEBOUNCE
     * ============================================================ */

    function debounce(
        callback,
        delayMs
    ) {

        let timerId;

        return (...args) => {

            window.clearTimeout(
                timerId
            );

            timerId =
                window.setTimeout(
                    () => {
                        callback(
                            ...args
                        );
                    },
                    delayMs
                );
        };
    }

    /* ============================================================
     * HELPERS
     * ============================================================ */

    function firstDefined(
        ...values
    ) {

        return values.find(
            (value) =>
                value !== undefined &&
                value !== null
        );
    }

    function asPositiveInteger(
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

    function asNonNegativeInteger(
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

    function refreshIcons() {

        if (
            window.lucide &&
            typeof window.lucide
                .createIcons ===
                'function'
        ) {
            window.lucide.createIcons();
        }
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

        return String(value)
            .replace(
                /["\\]/g,
                '\\$&'
            );
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
            state.currentPage >=
                state.lastPage;
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
                !button.dataset
                    .originalHtml
            ) {

                button.dataset
                    .originalHtml =
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

            if (busyText) {

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
            button.dataset
                .originalHtml
        ) {

            button.innerHTML =
                button.dataset
                    .originalHtml;

            delete button.dataset
                .originalHtml;

            refreshIcons();
        }
    }

})();
