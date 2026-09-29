import { createFocusTrap } from '@/core/focus-trap';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';

const SEARCH_LOCK = Symbol('navigation-search');

function normalizeSearch(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('es')
        .trim();
}

function isEditableTarget(target) {
    return target instanceof HTMLElement && (
        target.isContentEditable
        || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
    );
}

export class SearchController {
    constructor({ onBeforeOpen = null } = {}) {
        this.documentRoot = document.documentElement;
        this.root = document.querySelector('[data-navigation-search]');
        this.openButton = document.querySelector('[data-mobile-search-trigger]');
        this.closeButton = document.querySelector('[data-mobile-search-close]');
        this.input = document.querySelector('[data-navigation-search-input]');
        this.results = document.querySelector('[data-navigation-search-results]');
        this.empty = document.querySelector('[data-navigation-search-empty]');
        this.sidebar = document.querySelector('[data-shell-sidebar]');
        this.main = document.querySelector('main');
        this.footer = document.querySelector('footer');
        this.background = Array.from(document.querySelectorAll('[data-search-background]'));

        this.onBeforeOpen = onBeforeOpen;
        this.mobileMedia = window.matchMedia('(max-width: 767px)');
        this.items = [];
        this.matches = [];
        this.activeIndex = -1;
        this.enabled = false;
        this.opener = null;

        this.focusTrap = createFocusTrap(this.root, {
            onEscape: () => this.closeMobile({ restoreFocus: true }),
        });
    }

    init() {
        if (!this.root || !this.input || !this.results) return;

        this.openButton?.addEventListener('click', () => this.openMobile());
        this.closeButton?.addEventListener('click', () => this.closeMobile({ restoreFocus: true }));
        this.root.addEventListener('click', (event) => {
            if (event.target === this.root) {
                this.closeMobile({ restoreFocus: true });
            }
        });

        this.input.addEventListener('focus', () => this.renderResults());
        this.input.addEventListener('input', () => this.renderResults());
        this.input.addEventListener('keydown', (event) => this.handleKeydown(event));

        this.results.addEventListener('click', (event) => {
            if (event.target.closest('a[href]')) this.close();
        });

        document.addEventListener('pointerdown', (event) => {
            if (
                !this.mobileMedia.matches
                && !this.root.contains(event.target)
                && !this.results.hidden
            ) {
                this.closeResults();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (
                this.enabled
                && (event.ctrlKey || event.metaKey)
                && event.key.toLocaleLowerCase('es') === 'k'
                && !isEditableTarget(event.target)
            ) {
                event.preventDefault();
                this.mobileMedia.matches
                    ? this.openMobile()
                    : this.input.focus();
            }
        });

        this.mobileMedia.addEventListener('change', () => {
            if (!this.mobileMedia.matches) this.closeMobile();
        });
    }

    setItems(items) {
        this.items = items;
        this.closeResults();
    }

    setEnabled(enabled) {
        this.enabled = enabled;

        if (!enabled) this.close();

        this.root.hidden = !enabled;
        if (this.openButton) this.openButton.hidden = !enabled;
    }

    openMobile() {
        if (!this.mobileMedia.matches || !this.enabled || this.isMobileOpen()) return;

        this.onBeforeOpen?.();
        this.opener = document.activeElement;
        this.documentRoot.dataset.mobileSearchOpen = 'true';
        this.root.setAttribute('role', 'dialog');
        this.root.setAttribute('aria-modal', 'true');
        this.root.setAttribute('aria-labelledby', 'navigation-search-title');
        this.openButton?.setAttribute('aria-expanded', 'true');
        this.setBackgroundInert(true);
        lockScroll(SEARCH_LOCK);
        this.renderResults();
        this.focusTrap.activate(this.input);
    }

    closeMobile({ restoreFocus = false } = {}) {
        if (!this.isMobileOpen()) {
            this.closeResults();
            return;
        }

        delete this.documentRoot.dataset.mobileSearchOpen;
        this.root.removeAttribute('role');
        this.root.removeAttribute('aria-modal');
        this.root.removeAttribute('aria-labelledby');
        this.openButton?.setAttribute('aria-expanded', 'false');
        this.setBackgroundInert(false);
        unlockScroll(SEARCH_LOCK);
        this.focusTrap.deactivate();
        this.closeResults();
        this.input.value = '';

        if (restoreFocus && this.opener instanceof HTMLElement) {
            this.opener.focus();
        }

        this.opener = null;
    }

    close({ restoreFocus = false } = {}) {
        this.closeMobile({ restoreFocus });
        this.closeResults();
    }

    isMobileOpen() {
        return this.documentRoot.dataset.mobileSearchOpen === 'true';
    }

    setBackgroundInert(inert) {
        if (this.sidebar) this.sidebar.inert = inert;
        if (this.main) this.main.inert = inert;
        if (this.footer) this.footer.inert = inert;
        this.background.forEach((element) => {
            element.inert = inert;
        });
    }

    handleKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.mobileMedia.matches
                ? this.closeMobile({ restoreFocus: true })
                : this.closeResults();
            return;
        }

        if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key) || this.matches.length === 0) {
            return;
        }

        event.preventDefault();

        if (event.key === 'ArrowDown') {
            this.activeIndex = (this.activeIndex + 1) % this.matches.length;
        } else if (event.key === 'ArrowUp') {
            this.activeIndex = (this.activeIndex - 1 + this.matches.length) % this.matches.length;
        } else {
            const selected = this.matches[Math.max(0, this.activeIndex)];
            if (selected) window.location.assign(selected.url);
            return;
        }

        this.updateActiveResult();
    }

    renderResults() {
        if (!this.enabled) return;

        const query = normalizeSearch(this.input.value);
        this.matches = this.items.filter((item) => {
            const haystack = normalizeSearch([
                item.label,
                item.group,
                ...item.keywords,
            ].join(' '));

            return !query || haystack.includes(query);
        });
        this.activeIndex = this.matches.length > 0 ? 0 : -1;
        this.results.replaceChildren();

        this.matches.forEach((item, index) => {
            const link = document.createElement('a');
            const icon = document.createElement('i');
            const label = document.createElement('span');
            const group = document.createElement('span');

            link.id = `navigation-search-result-${index}`;
            link.href = item.url;
            link.role = 'option';
            link.className = 'flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 no-underline hover:bg-gintly-control';
            link.setAttribute('aria-selected', String(index === this.activeIndex));

            icon.className = `fa-solid ${item.icon} w-5 text-center text-gintly-brand`;
            icon.setAttribute('aria-hidden', 'true');
            label.className = 'min-w-0 flex-1 truncate text-sm font-semibold text-black';
            label.textContent = item.label;
            group.className = 'text-xs text-gintly-text-secondary';
            group.textContent = item.group;

            link.append(icon, label, group);
            this.results.appendChild(link);
        });

        this.results.hidden = this.matches.length === 0;
        this.empty.hidden = this.matches.length > 0;
        this.input.setAttribute('aria-expanded', 'true');
        this.updateActiveResult();
    }

    updateActiveResult() {
        const options = Array.from(this.results.querySelectorAll('[role="option"]'));

        options.forEach((option, index) => {
            const active = index === this.activeIndex;
            option.setAttribute('aria-selected', String(active));
            option.classList.toggle('bg-gintly-control', active);
        });

        const active = options[this.activeIndex];
        if (active) {
            this.input.setAttribute('aria-activedescendant', active.id);
            active.scrollIntoView({ block: 'nearest' });
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    }

    closeResults() {
        if (!this.results) return;

        this.results.hidden = true;
        this.empty.hidden = true;
        this.input?.setAttribute('aria-expanded', 'false');
        this.input?.removeAttribute('aria-activedescendant');
        this.matches = [];
        this.activeIndex = -1;
    }
}
