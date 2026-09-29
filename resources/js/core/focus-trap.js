const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

function focusableElements(container) {
    return Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR))
        .filter((element) => (
            !element.hidden &&
            element.getAttribute('aria-hidden') !== 'true' &&
            element.getClientRects().length > 0
        ));
}

export function createFocusTrap(container, { onEscape = null } = {}) {
    let active = false;

    const onKeydown = (event) => {
        if (!active) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            onEscape?.();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = focusableElements(container);

        if (focusable.length === 0) {
            event.preventDefault();
            container.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable.at(-1);

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    return {
        activate(initialFocus = null) {
            if (active) {
                return;
            }

            active = true;
            document.addEventListener('keydown', onKeydown);

            const target = initialFocus ?? focusableElements(container)[0] ?? container;
            window.requestAnimationFrame(() => target.focus());
        },

        deactivate() {
            if (!active) {
                return;
            }

            active = false;
            document.removeEventListener('keydown', onKeydown);
        },
    };
}
