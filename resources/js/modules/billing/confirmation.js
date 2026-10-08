import { createFocusTrap } from '@/core/focus-trap';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';

export function confirmation(root) {
    const overlay = root.matches('[data-billing-confirmation]') ? root : root.querySelector('[data-billing-confirmation]');
    const dialog = overlay.querySelector('[role="dialog"]'); let resolve = null, trigger = null;
    const trap = createFocusTrap(dialog, { onEscape: () => close(false) });
    function close(answer) {
        if (!resolve) return;
        overlay.hidden = true; trap.deactivate(); unlockScroll(overlay); trigger?.focus();
        const done = resolve; resolve = null; done(answer);
        if (!answer) queueMicrotask(() => trigger?.focus());
    }
    overlay.querySelector('[data-confirm-no]').addEventListener('click', () => close(false));
    overlay.querySelector('[data-confirm-yes]').addEventListener('click', () => close(true));
    overlay.addEventListener('click', event => { if (event.target === overlay) close(false); });
    return { ask(text, source) { if (resolve) return Promise.resolve(false); trigger = source; overlay.querySelector('[data-confirm-description]').textContent = text; overlay.hidden = false; lockScroll(overlay); trap.activate(overlay.querySelector('[data-confirm-no]')); return new Promise(done => { resolve = done; }); }, destroy() { close(false); } };
}
