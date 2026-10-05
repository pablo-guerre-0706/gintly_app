import { ApiError } from '@/core/api-client';
import { notify } from '@/core/notifications';
import { getSessionContext, SessionContextError } from '@/core/session-context';
import { AccountMenuController } from './account-menu-controller';
import { AnomalyBellController } from './anomaly-bell-controller';
import { initLogout } from './logout';
import { authorizedNavigation, flattenedNavigation, isCurrentPageAuthorized } from './navigation';
import { SearchController } from './search-controller';
import { SidebarController } from './sidebar-controller';

let shellPromise = null;

function contextErrorMessage(error) {
    if (error instanceof ApiError) {
        if (error.status === 403) return 'La cuenta no tiene acceso operativo al panel.';
        if (error.status === 419) return 'La sesión de seguridad expiró. Recargue la página para continuar.';
        if (error.status === 0) return 'No fue posible conectar con el servidor.';
        if (error.status >= 500) return 'El servidor no pudo cargar el contexto de la cuenta.';
    }

    if (error instanceof SessionContextError) return error.message;

    return 'No fue posible validar el acceso al panel.';
}

class PanelShellCoordinator {
    constructor(root) {
        this.root = root;
        this.logoutButtons = Array.from(document.querySelectorAll('[data-logout]'));
        this.contextRequest = null;

        this.urls = {
            dashboard: root.dataset.urlDashboard,
            pos: root.dataset.urlPos,
            salesSummary: root.dataset.urlSalesSummary,
            customers: root.dataset.urlCustomers,
            customersCreate: root.dataset.urlCustomersCreate,
            inventoryReconciliation: root.dataset.urlInventoryReconciliation,
            inventorySummary: root.dataset.urlInventorySummary,
            inventoryStock: root.dataset.urlInventoryStock,
            catalogProducts: root.dataset.urlCatalogProducts,
            purchasesHub: root.dataset.urlPurchasesHub,
            financeHub: root.dataset.urlFinanceHub,
            cashOverview: root.dataset.urlCashOverview,
            receivables: root.dataset.urlReceivables,
            payables: root.dataset.urlPayables,
            intelligenceHub: root.dataset.urlIntelligenceHub,
            suppliers: root.dataset.urlSuppliers,
            externalSuppliers: root.dataset.urlExternalSuppliers,
            anomalies: root.dataset.urlAnomalies,
            reconciliations: root.dataset.urlReconciliations,
            kpiSnapshots: root.dataset.urlKpiSnapshots,
            reportDefinitions: root.dataset.urlReportDefinitions,
            audit: root.dataset.urlAudit,
            organizationHub: root.dataset.urlOrganizationHub,
            configurationHub: root.dataset.urlConfigurationHub,
            help: root.dataset.urlHelp,
            users: root.dataset.urlUsers,
            profiles: root.dataset.urlProfiles,
            branches: root.dataset.urlBranches,
            branchesCreate: root.dataset.urlBranchesCreate,
            branchesEdit: root.dataset.urlBranchesEditTemplate,
            adminInvoices: root.dataset.urlAdminInvoices,
            adminWarehouses: root.dataset.urlAdminWarehouses,
            adminPhysicalCounts: root.dataset.urlAdminPhysicalCounts,
            adminPurchaseOrders: root.dataset.urlAdminPurchaseOrders,
            adminGoodsReceipts: root.dataset.urlAdminGoodsReceipts,
            adminCashRegisters: root.dataset.urlAdminCashRegisters,
            adminCashSessions: root.dataset.urlAdminCashSessions,
            adminExchangeRates: root.dataset.urlAdminExchangeRates,
            operativeCash: root.dataset.urlOperativeCash,
            operativeCashOpen: root.dataset.urlOperativeCashOpen,
            operativeCashMovements: root.dataset.urlOperativeCashMovements,
            operativeCashCount: root.dataset.urlOperativeCashCount,
            operativeCashClose: root.dataset.urlOperativeCashClose,
            operativeCashHistory: root.dataset.urlOperativeCashHistory,
            operativeReceivables: root.dataset.urlOperativeReceivables,
            operativeStock: root.dataset.urlOperativeStock,
            operativePhysicalCount: root.dataset.urlOperativePhysicalCount,
            operativeDispatches: root.dataset.urlOperativeDispatches,
            operativeInvoices: root.dataset.urlOperativeInvoices,
            operativeSales: root.dataset.urlOperativeSales,
            operativeWarehouses: root.dataset.urlOperativeWarehouses,
            operativeTransfers: root.dataset.urlOperativeTransfers,
            operativeTransfersCreate: root.dataset.urlOperativeTransfersCreate,
            operativePurchaseOrders: root.dataset.urlOperativePurchaseOrders,
            operativePurchaseOrdersCreate: root.dataset.urlOperativePurchaseOrdersCreate,
            operativeGoodsReceipts: root.dataset.urlOperativeGoodsReceipts,
            operativeGoodsReceiptsCreate: root.dataset.urlOperativeGoodsReceiptsCreate,
            operativeSuppliers: root.dataset.urlOperativeSuppliers,
            operativePayables: root.dataset.urlOperativePayables,
            operativeReturns: root.dataset.urlOperativeReturns,
            operativeReturnsCreate: root.dataset.urlOperativeReturnsCreate,
            operativeCreditNotes: root.dataset.urlOperativeCreditNotes,
        };

        this.sidebar = new SidebarController(root, {
            onBeforeOpen: () => {
                this.account.close();
                this.search.close();
                this.anomalyBell.close();
            },
            onRetry: () => this.loadContext({ refresh: true }),
        });
        this.search = new SearchController({
            onBeforeOpen: () => {
                this.sidebar.close();
                this.account.close();
                this.anomalyBell.close();
            },
        });
        this.account = new AccountMenuController({
            onBeforeOpen: () => {
                this.sidebar.close();
                this.search.close();
                this.anomalyBell.close();
            },
        });
        this.anomalyBell = new AnomalyBellController({
            onBeforeOpen: () => {
                this.sidebar.close();
                this.search.close();
                this.account.close();
            },
        });
    }

    init() {
        this.sidebar.init();
        this.search.init();
        this.account.init();
        this.anomalyBell.init();

        initLogout({
            loginUrl: this.root.dataset.urlLogin,
            beforeLogout: () => this.closeTransientSurfaces(),
        });

        return this.loadContext();
    }

    loadContext({ refresh = false } = {}) {
        if (this.contextRequest) return this.contextRequest;

        this.setLoading();
        this.contextRequest = getSessionContext({ refresh })
            .then((context) => this.applyContext(context))
            .catch((error) => this.applyContextError(error))
            .finally(() => {
                this.contextRequest = null;
            });

        return this.contextRequest;
    }

    applyContext(context) {
        const groups = authorizedNavigation(context, this.urls);
        const items = flattenedNavigation(groups);

        this.sidebar.renderNavigation(groups);
        this.search.setItems(items);
        this.account.render(context);
        this.anomalyBell.setContext(context);
        this.setReady();

        if (!isCurrentPageAuthorized(context, this.urls)) {
            this.renderAccessDenied();
            return false;
        }

        document.dispatchEvent(new CustomEvent('gintly:session-ready', {
            detail: context,
        }));

        return true;
    }

    applyContextError(error) {
        const message = contextErrorMessage(error);

        this.sidebar.setError(message);
        this.search.setEnabled(false);
        this.account.setEnabled(false);
        this.anomalyBell.setUnavailable();
        this.setLogoutVisible(false);

        notify({
            type: 'error',
            title: 'Panel no disponible',
            message,
            persistent: true,
        });

        return false;
    }

    renderAccessDenied() {
        const main = document.querySelector('#main-content');
        const wrapper = document.createElement('div');
        const card = document.createElement('section');
        const title = document.createElement('h1');
        const message = document.createElement('p');
        const link = document.createElement('a');

        wrapper.className = 'mx-auto w-full max-w-[1512px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8';
        card.className = 'rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-950';
        card.setAttribute('role', 'alert');
        title.className = 'text-lg font-bold';
        title.textContent = 'Vista no disponible para tu experiencia';
        message.className = 'mt-2 text-sm leading-6';
        message.textContent = 'Este destino no corresponde al rol, las capacidades o los perfiles del contexto autenticado.';
        link.className = 'mt-4 inline-flex min-h-11 items-center rounded-xl bg-gintly-brand px-4 text-sm font-semibold text-white';
        link.href = this.urls.dashboard;
        link.textContent = 'Volver al dashboard';
        card.append(title, message, link);
        wrapper.appendChild(card);
        main?.replaceChildren(wrapper);
        main?.focus();
    }

    setLoading() {
        this.sidebar.setLoading();
        this.search.setEnabled(false);
        this.account.setEnabled(false);
        this.anomalyBell.setUnavailable();
        this.setLogoutVisible(false);
    }

    setReady() {
        this.sidebar.setReady();
        this.search.setEnabled(true);
        this.account.setEnabled(true);
        this.setLogoutVisible(true);
    }

    setLogoutVisible(visible) {
        this.logoutButtons.forEach((button) => {
            button.hidden = !visible;
        });
    }

    closeTransientSurfaces() {
        this.account.close();
        this.anomalyBell.close();
        this.search.close();
        this.sidebar.close();
    }
}

export function initPanelShell() {
    const root = document.querySelector('[data-panel-shell]');

    if (!root) return Promise.resolve(true);
    if (shellPromise) return shellPromise;

    root.dataset.shellInitialized = 'true';
    shellPromise = new PanelShellCoordinator(root).init();
    return shellPromise;
}
