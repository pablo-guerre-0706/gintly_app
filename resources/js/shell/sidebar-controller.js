import { createFocusTrap } from '@/core/focus-trap';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';

const DRAWER_LOCK = Symbol('panel-drawer');
const SIDEBAR_PREFERENCE_KEY = 'gintly:shell:sidebar-collapsed';

function normalizedPath(url) {
    try {
        const path = new URL(url, window.location.origin).pathname.replace(/\/+$/, '');

        return path || '/';
    } catch {
        return null;
    }
}

export class SidebarController {
    constructor(root, { onBeforeOpen = null, onRetry = null } = {}) {
        this.documentRoot = document.documentElement;
        this.surface = root.querySelector('[data-shell-surface]');
        this.sidebar = document.querySelector('[data-shell-sidebar]');
        this.backdrop = document.querySelector('[data-shell-backdrop]');
        this.drawerTrigger = document.querySelector('[data-drawer-trigger]');
        this.toggleButton = document.querySelector('[data-sidebar-toggle]');
        this.navigation = document.querySelector('[data-sidebar-navigation]');
        this.loading = document.querySelector('[data-sidebar-loading]');
        this.error = document.querySelector('[data-sidebar-error]');
        this.errorMessage = document.querySelector('[data-sidebar-error-message]');
        this.retryButton = document.querySelector('[data-session-retry]');
        this.tooltip = document.querySelector('[data-sidebar-tooltip]');

        this.onBeforeOpen = onBeforeOpen;
        this.onRetry = onRetry;
        this.drawerOpener = null;

        this.mobileMedia = window.matchMedia('(max-width: 1023px)');
        this.desktopMedia = window.matchMedia('(min-width: 1280px)');
        this.drawerTrap = createFocusTrap(this.sidebar, {
            onEscape: () => this.closeDrawer({ restoreFocus: true }),
        });
    }

    init() {
        if (!this.sidebar || !this.surface) {
            return;
        }

        this.restorePreference();
        this.drawerTrigger?.addEventListener('click', () => this.openDrawer());
        this.backdrop?.addEventListener('click', () => this.closeDrawer({ restoreFocus: true }));
        this.toggleButton?.addEventListener('click', () => this.toggle());
        this.retryButton?.addEventListener('click', () => this.onRetry?.());

        this.navigation?.addEventListener('click', (event) => {
            if (event.target.closest('a[href]')) {
                this.closeDrawer();
                this.closeRailOverlay();
            }
        });

        this.sidebar.addEventListener('pointerover', (event) => {
            const target = event.target.closest('[data-nav-tooltip]');
            if (target) this.showTooltip(target);
        });
        this.sidebar.addEventListener('pointerout', (event) => {
            const target = event.target.closest('[data-nav-tooltip]');
            if (target && !target.contains(event.relatedTarget)) this.hideTooltip();
        });
        this.sidebar.addEventListener('focusin', (event) => {
            const target = event.target.closest('[data-nav-tooltip]');
            if (target) this.showTooltip(target);
        });
        this.sidebar.addEventListener('focusout', (event) => {
            const target = event.target.closest('[data-nav-tooltip]');
            if (target && !target.contains(event.relatedTarget)) this.hideTooltip();
        });

        document.addEventListener('pointerdown', (event) => {
            if (this.isRailOverlayOpen() && !this.sidebar.contains(event.target)) {
                this.closeRailOverlay();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.isRailOverlayOpen()) {
                this.closeRailOverlay({ restoreFocus: true });
            }
        });

        [this.mobileMedia, this.desktopMedia].forEach((media) => {
            media.addEventListener('change', () => this.syncResponsiveState());
        });

        this.syncResponsiveState();
    }

    restorePreference() {
        let collapsed = false;

        try {
            collapsed = localStorage.getItem(SIDEBAR_PREFERENCE_KEY) === 'true';
        } catch {
            // La preferencia es opcional y no afecta la autorización ni la sesión.
        }

        this.documentRoot.dataset.sidebarCollapsed = String(collapsed);
    }

    persistPreference(collapsed) {
        this.documentRoot.dataset.sidebarCollapsed = String(collapsed);

        try {
            localStorage.setItem(SIDEBAR_PREFERENCE_KEY, String(collapsed));
        } catch {
            // El shell sigue siendo funcional cuando el almacenamiento está bloqueado.
        }
    }

    toggle() {
        if (this.mobileMedia.matches) {
            this.closeDrawer({ restoreFocus: true });
            return;
        }

        if (!this.desktopMedia.matches) {
            this.isRailOverlayOpen()
                ? this.closeRailOverlay({ restoreFocus: true })
                : this.openRailOverlay();
            return;
        }

        const collapsed = this.documentRoot.dataset.sidebarCollapsed === 'true';
        this.persistPreference(!collapsed);
        this.updateControls();
        this.hideTooltip();
    }

    openDrawer() {
        if (!this.mobileMedia.matches || this.isDrawerOpen()) {
            return;
        }

        this.onBeforeOpen?.();
        this.drawerOpener = document.activeElement;
        this.documentRoot.dataset.shellDrawerOpen = 'true';
        this.backdrop.hidden = false;
        this.surface.inert = true;
        lockScroll(DRAWER_LOCK);
        this.updateControls();
        this.drawerTrap.activate(this.toggleButton);
    }

    closeDrawer({ restoreFocus = false } = {}) {
        if (!this.isDrawerOpen()) {
            return;
        }

        delete this.documentRoot.dataset.shellDrawerOpen;
        this.backdrop.hidden = true;
        this.surface.inert = false;
        unlockScroll(DRAWER_LOCK);
        this.drawerTrap.deactivate();
        this.updateControls();

        if (restoreFocus && this.drawerOpener instanceof HTMLElement) {
            this.drawerOpener.focus();
        }

        this.drawerOpener = null;
    }

    openRailOverlay() {
        if (this.mobileMedia.matches || this.desktopMedia.matches || this.isRailOverlayOpen()) {
            return;
        }

        this.onBeforeOpen?.();
        this.documentRoot.dataset.sidebarOverlayOpen = 'true';
        this.updateControls();
        this.hideTooltip();
    }

    closeRailOverlay({ restoreFocus = false } = {}) {
        if (!this.isRailOverlayOpen()) {
            return;
        }

        delete this.documentRoot.dataset.sidebarOverlayOpen;
        this.updateControls();

        if (restoreFocus) {
            this.toggleButton?.focus();
        }
    }

    close({ restoreFocus = false } = {}) {
        this.closeDrawer({ restoreFocus });
        this.closeRailOverlay({ restoreFocus });
    }

    syncResponsiveState() {
        if (!this.mobileMedia.matches) {
            this.closeDrawer();
        }

        if (this.mobileMedia.matches || this.desktopMedia.matches) {
            this.closeRailOverlay();
        }

        this.updateControls();
        this.hideTooltip();
    }

    updateControls() {
        const drawerOpen = this.isDrawerOpen();
        const overlayOpen = this.isRailOverlayOpen();
        const collapsed = this.documentRoot.dataset.sidebarCollapsed === 'true';
        const expanded = this.mobileMedia.matches
            ? drawerOpen
            : this.desktopMedia.matches
                ? !collapsed
                : overlayOpen;

        this.drawerTrigger?.setAttribute('aria-expanded', String(drawerOpen));
        this.toggleButton?.setAttribute('aria-expanded', String(expanded));

        const label = this.mobileMedia.matches
            ? 'Cerrar navegación'
            : expanded
                ? 'Colapsar navegación'
                : 'Expandir navegación';

        this.toggleButton?.setAttribute('aria-label', label);
        this.toggleButton?.setAttribute('title', label);

        if (this.toggleButton) {
            this.toggleButton.dataset.navTooltip = label;
        }

        const icon = this.toggleButton?.querySelector('i');
        if (!icon) return;

        icon.classList.remove('fa-angles-left', 'fa-angles-right', 'fa-xmark');
        icon.classList.add(
            this.mobileMedia.matches
                ? 'fa-xmark'
                : expanded
                    ? 'fa-angles-left'
                    : 'fa-angles-right',
        );
    }

    isDrawerOpen() {
        return this.documentRoot.dataset.shellDrawerOpen === 'true';
    }

    isRailOverlayOpen() {
        return this.documentRoot.dataset.sidebarOverlayOpen === 'true';
    }

    isRailMode() {
        if (this.mobileMedia.matches) return false;

        return this.desktopMedia.matches
            ? this.documentRoot.dataset.sidebarCollapsed === 'true'
            : !this.isRailOverlayOpen();
    }

    showTooltip(target) {
        if (!this.tooltip || !this.isRailMode()) return;

        const label = target.dataset.navTooltip;
        if (!label) return;

        this.tooltip.textContent = label;
        this.tooltip.hidden = false;
        target.setAttribute('aria-describedby', this.tooltip.id);

        const rect = target.getBoundingClientRect();
        const tooltipRect = this.tooltip.getBoundingClientRect();
        this.tooltip.style.left = `${rect.right + 12}px`;
        this.tooltip.style.top = `${Math.max(8, rect.top + (rect.height - tooltipRect.height) / 2)}px`;
    }

    hideTooltip() {
        if (!this.tooltip) return;

        this.tooltip.hidden = true;
        this.tooltip.removeAttribute('style');
        this.sidebar?.querySelectorAll('[aria-describedby]')
            .forEach((target) => target.removeAttribute('aria-describedby'));
    }

    setLoading() {
        this.loading.hidden = false;
        this.error.hidden = true;
        this.navigation.hidden = true;
        this.retryButton.disabled = true;
    }

    setReady() {
        this.loading.hidden = true;
        this.error.hidden = true;
        this.navigation.hidden = false;
        this.retryButton.disabled = false;
    }

    setError(message) {
        this.loading.hidden = true;
        this.navigation.hidden = true;
        this.error.hidden = false;
        this.retryButton.disabled = false;
        this.errorMessage.textContent = message;
    }

    renderNavigation(groups) {
        this.navigation.replaceChildren();
        const currentPath = normalizedPath(window.location.href);

        groups.forEach((group) => {
            const section = document.createElement('section');
            const heading = document.createElement('h2');
            const list = document.createElement('ul');

            heading.className = 'shell-sidebar-label px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500';
            heading.textContent = group.label;
            list.className = 'mt-2 space-y-1';

            group.items.forEach((item) => {
                const listItem = document.createElement('li');
                const link = document.createElement('a');
                const icon = document.createElement('i');
                const label = document.createElement('span');
                const active = normalizedPath(item.url) === currentPath;

                link.href = item.url;
                link.className = [
                    'shell-nav-link flex min-h-11 items-center gap-3 rounded-[14px] px-3 py-2.5 text-sm font-medium no-underline transition-colors',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-active',
                    active
                        ? 'bg-gintly-active font-bold text-gintly-sidebar'
                        : 'text-white/75 hover:bg-white/10 hover:text-white',
                ].join(' ');
                link.dataset.navTooltip = item.label;
                link.setAttribute('aria-label', item.label);
                if (active) link.setAttribute('aria-current', 'page');

                icon.className = `fa-solid ${item.icon} w-11 shrink-0 text-center text-base`;
                icon.setAttribute('aria-hidden', 'true');
                label.className = 'shell-sidebar-label min-w-0 truncate';
                label.textContent = item.label;

                link.append(icon, label);
                listItem.appendChild(link);
                list.appendChild(listItem);
            });

            section.append(heading, list);
            this.navigation.appendChild(section);
        });
    }
}
