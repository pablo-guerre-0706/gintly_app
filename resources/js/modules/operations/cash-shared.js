import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { money } from '@/core/money';

const AMOUNT = /^\d+(?:\.\d{1,2})?$/;

export function cashAmount(value) {
    const input = String(value ?? '').trim();
    return AMOUNT.test(input) ? money(input) : null;
}

export function cashLabel(value, currency = 'NIO') {
    return `${currency === 'USD' ? 'US$' : 'C$'} ${money(String(value ?? '0.00'))}`;
}

export function cashError(error) {
    if (!(error instanceof ApiError)) return 'No fue posible completar la operación. Comprueba su estado antes de repetirla.';
    if (error.status === 401) {
        const login = document.querySelector('meta[name="login-url"]')?.content;
        if (login) window.location.assign(login);
        return 'La sesión expiró. Inicia sesión nuevamente.';
    }
    if (error.status === 403) return 'No tienes autorización para esta operación.';
    if (error.status === 404) return 'La sesión o caja ya no está disponible.';
    if (error.status === 409) return error.message || 'La caja cambió de estado. Actualiza antes de continuar.';
    if (error.status === 419) return 'La sesión de seguridad expiró. Vuelve a intentarlo.';
    if (error.status === 422) return error.message || 'Corrige los campos señalados.';
    if (error.status === 429) return 'Demasiadas solicitudes. Espera un momento antes de continuar.';
    if (error.status >= 500 || error.status === 0) return 'No se confirmó el resultado. Consulta el estado de la sesión antes de intentar otra vez.';
    return error.message;
}

export async function cashRead(path, query = {}, signal = null) {
    try { return await api.get(path, query, { signal, dispatchErrors: false }); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal?.aborted) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.get(path, query, { signal, dispatchErrors: false });
    }
}

export async function cashPost(path, payload, expectedStatus) {
    const send = () => api.post(path, payload, { dispatchErrors: false, expectedStatus });
    try { return await send(); }
    catch (error) {
        // Laravel rechaza 419 antes de ejecutar la mutación. Ningún otro fallo se reintenta.
        if (!(error instanceof ApiError) || error.status !== 419) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return send();
    }
}

export async function cashierContext(capability = null) {
    const context = await getSessionContext();
    if (context.role !== 'ROL-03' || !context.profiles.includes('cajero') || !context.branch?.id
        || (capability && !context.capabilities.includes(capability))) {
        throw new TypeError('Tu contexto no autoriza esta operación de caja.');
    }
    return context;
}

export async function currentCashSession(context) {
    const response = await cashRead('/cash-sessions/current');
    if (!response || !Object.hasOwn(response, 'data')) throw new TypeError('El servidor no devolvió un estado de caja válido.');
    const session = response.data;
    if (session !== null && (!Number.isInteger(session.id) || session.status !== 'abierta'
        || session.opened_by !== context.identity.id || session.cash_register?.branch_id !== context.branch.id
        || session.expected_amount !== null || session.difference !== null
        || session.expected_amount_usd !== null || session.difference_usd !== null)) {
        throw new TypeError('La sesión recibida contradice el contexto o el arqueo ciego.');
    }
    return session;
}

export function cashUrls() {
    const root = document.querySelector('[data-panel-shell]');
    return {
        summary: root?.dataset.urlOperativeCash,
        open: root?.dataset.urlOperativeCashOpen,
        count: root?.dataset.urlOperativeCashCount,
        close: root?.dataset.urlOperativeCashClose,
        movements: root?.dataset.urlOperativeCashMovements,
        history: root?.dataset.urlOperativeCashHistory,
    };
}

export function cashNotice(root, text, error = false) {
    const notice = root.querySelector('[data-cash-notice]');
    notice.textContent = text;
    notice.classList.toggle('border-red-200', error);
    notice.classList.toggle('bg-red-50', error);
    notice.classList.toggle('text-red-900', error);
    notice.hidden = false;
    notice.focus();
}

export function cashFieldErrors(form, errors = {}) {
    for (const input of form.querySelectorAll('[aria-invalid="true"]')) input.removeAttribute('aria-invalid');
    for (const message of form.querySelectorAll('[data-error-for]')) message.hidden = true;
    let first = null;
    for (const [key, messages] of Object.entries(errors)) {
        const name = key.split('.')[0];
        const input = form.elements.namedItem(name);
        const message = Array.from(form.querySelectorAll('[data-error-for]')).find((node) => node.dataset.errorFor === name);
        if (input instanceof HTMLElement) { input.setAttribute('aria-invalid', 'true'); first ??= input; }
        if (message) { message.textContent = Array.isArray(messages) ? messages[0] : String(messages); message.hidden = false; }
    }
    first?.focus();
    return first;
}

export function cashBusy(form, busy, label = 'Guardando…') {
    const button = form.querySelector('[type="submit"]');
    if (!button) return;
    if (!button.dataset.originalLabel) button.dataset.originalLabel = button.textContent;
    button.disabled = busy;
    button.textContent = busy ? label : button.dataset.originalLabel;
    form.setAttribute('aria-busy', String(busy));
}
