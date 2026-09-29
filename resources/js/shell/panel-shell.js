import { ApiError } from '@/core/api-client';
import { notify } from '@/core/notifications';
import { getSessionContext, SessionContextError } from '@/core/session-context';
import { AccountMenuController } from './account-menu-controller';
import { initLogout } from './logout';
import { authorizedNavigation, flattenedNavigation } from './navigation';
import { SearchController } from './search-controller';
import { SidebarController } from './sidebar-controller';

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
            cashClosing: root.dataset.urlCashClosing,
            customers: root.dataset.urlCustomers,
            customerCreate: root.dataset.urlCustomerCreate,
            inventoryReconciliation: root.dataset.urlInventoryReconciliation,
            catalogProducts: root.dataset.urlCatalogProducts,
        };

        this.sidebar = new SidebarController(root, {
            onBeforeOpen: () => {
                this.account.close();
                this.search.close();
            },
            onRetry: () => this.loadContext({ refresh: true }),
        });
        this.search = new SearchController({
            onBeforeOpen: () => {
                this.sidebar.close();
                this.account.close();
            },
        });
        this.account = new AccountMenuController({
            onBeforeOpen: () => {
                this.sidebar.close();
                this.search.close();
            },
        });
    }

    init() {
        this.sidebar.init();
        this.search.init();
        this.account.init();

        initLogout({
            loginUrl: this.root.dataset.urlLogin,
            beforeLogout: () => this.closeTransientSurfaces(),
        });

        this.loadContext();
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
        this.setReady();

        document.dispatchEvent(new CustomEvent('gintly:session-ready', {
            detail: context,
        }));
    }

    applyContextError(error) {
        const message = contextErrorMessage(error);

        this.sidebar.setError(message);
        this.search.setEnabled(false);
        this.account.setEnabled(false);
        this.setLogoutVisible(false);

        notify({
            type: 'error',
            title: 'Panel no disponible',
            message,
            persistent: true,
        });
    }

    setLoading() {
        this.sidebar.setLoading();
        this.search.setEnabled(false);
        this.account.setEnabled(false);
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
        this.search.close();
        this.sidebar.close();
    }
}

export function initPanelShell() {
    const root = document.querySelector('[data-panel-shell]');

    if (!root || root.dataset.shellInitialized === 'true') return;

    root.dataset.shellInitialized = 'true';
    new PanelShellCoordinator(root).init();
}
