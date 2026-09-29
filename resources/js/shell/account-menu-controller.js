function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toLocaleUpperCase('es'))
        .join('');
}

function setText(element, value) {
    if (element) element.textContent = value;
}

export class AccountMenuController {
    constructor({ onBeforeOpen = null } = {}) {
        this.trigger = document.querySelector('[data-account-trigger]');
        this.menu = document.querySelector('[data-account-menu]');
        this.onBeforeOpen = onBeforeOpen;
        this.opener = null;
        this.context = null;
    }

    init() {
        if (!this.trigger || !this.menu) return;

        this.trigger.addEventListener('click', () => {
            this.isOpen()
                ? this.close({ restoreFocus: true })
                : this.open();
        });

        document.addEventListener('pointerdown', (event) => {
            if (
                this.isOpen()
                && !this.menu.contains(event.target)
                && !this.trigger.contains(event.target)
            ) {
                this.close();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.isOpen()) {
                event.preventDefault();
                this.close({ restoreFocus: true });
            }
        });
    }

    setEnabled(enabled) {
        if (!enabled) this.close();

        this.trigger.disabled = !enabled;
        this.trigger.setAttribute('aria-busy', String(!enabled));
    }

    open() {
        if (!this.context || this.trigger.disabled || this.isOpen()) return;

        this.onBeforeOpen?.();
        this.opener = document.activeElement;
        this.menu.hidden = false;
        this.trigger.setAttribute('aria-expanded', 'true');

        window.requestAnimationFrame(() => {
            this.menu.querySelector('[data-logout]')?.focus();
        });
    }

    close({ restoreFocus = false } = {}) {
        if (!this.isOpen()) return;

        this.menu.hidden = true;
        this.trigger.setAttribute('aria-expanded', 'false');

        if (restoreFocus && this.opener instanceof HTMLElement) {
            this.opener.focus();
        }

        this.opener = null;
    }

    isOpen() {
        return this.trigger?.getAttribute('aria-expanded') === 'true';
    }

    render(context) {
        this.context = context;
        const { identity, role, roleLabel, profileLabels, business, branch } = context;

        setText(document.querySelector('[data-user-initials]'), initials(identity.name));
        setText(document.querySelector('[data-user-name]'), identity.name);
        setText(document.querySelector('[data-user-role]'), roleLabel);
        setText(document.querySelector('[data-account-name]'), identity.name);
        setText(document.querySelector('[data-account-email]'), identity.email);
        setText(document.querySelector('[data-account-role]'), `${roleLabel} · ${role}`);

        const email = document.querySelector('[data-account-email]');
        if (email) email.hidden = !identity.email;

        this.renderOptionalRow(
            '[data-account-status-row]',
            '[data-account-status]',
            identity.isActive ? 'Cuenta activa' : 'Cuenta inactiva',
        );
        this.renderOptionalRow(
            '[data-account-profiles-row]',
            '[data-account-profiles]',
            role === 'ROL-03' ? profileLabels.join(', ') : '',
        );
        this.renderOptionalRow(
            '[data-account-business-row]',
            '[data-account-business]',
            business.name,
        );
        this.renderOptionalRow(
            '[data-account-branch-row]',
            '[data-account-branch]',
            branch ? `Sucursal #${branch.id}` : '',
        );
    }

    renderOptionalRow(rowSelector, valueSelector, value) {
        const row = document.querySelector(rowSelector);
        const target = document.querySelector(valueSelector);
        if (!row || !target) return;

        target.textContent = value;
        row.hidden = !value;
    }
}
