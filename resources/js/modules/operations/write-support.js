import { api, ApiError, initializeCsrf } from '@/core/api-client';

export const DECIMAL_2 = /^\d+(?:\.\d{1,2})?$/;
export const DECIMAL_3 = /^\d+(?:\.\d{1,3})?$/;
export const DECIMAL_4 = /^\d+(?:\.\d{1,4})?$/;

export function errorMessage(error) {
    if (!(error instanceof ApiError)) return error instanceof TypeError ? error.message : 'No fue posible completar la operación.';
    if (error.status === 401) return 'La sesión expiró. Inicia sesión nuevamente.';
    if (error.status === 403) return 'No tienes autorización para esta operación.';
    if (error.status === 404) return 'El recurso ya no está disponible.';
    if (error.status === 409) return error.message || 'La operación presenta un conflicto.';
    if (error.status === 419) return 'La sesión de seguridad expiró. Vuelve a intentarlo.';
    if (error.status === 422) return 'Corrige los campos indicados.';
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes. Espera antes de reintentar.';
    if (error.status >= 500) return 'El servidor no pudo completar la operación. Verifica el estado antes de reintentar.';
    if (error.status === 0) return error.code === 'request_aborted' ? 'La solicitud fue interrumpida.' : 'No fue posible conectar. Verifica el estado antes de reintentar.';
    return error.message;
}

export async function read(path, query = {}, signal = null) {
    const options = { dispatchErrors: false, signal };
    try { return await api.get(path, query, options); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal?.aborted) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.get(path, query, options);
    }
}

export async function write(path, payload) {
    const options = { dispatchErrors: false };
    try { return await api.post(path, payload, options); }
    catch (error) {
        // Un 419 de Laravel se rechaza antes de ejecutar el controlador.
        if (!(error instanceof ApiError) || error.status !== 419) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.post(path, payload, options);
    }
}

export function requireBodeguero(context, capability) {
    if (context.role !== 'ROL-03' || !context.branch?.id || !context.profiles.includes('bodeguero') || !context.capabilities.includes(capability)) {
        throw new TypeError('Tu contexto no autoriza esta operación de bodega.');
    }
}

export function records(payload) {
    if (!payload || !Array.isArray(payload.data) || !payload.meta || typeof payload.meta !== 'object') {
        throw new TypeError('El servidor no devolvió una colección paginada válida.');
    }
    return payload.data;
}

export function options(select, items, label, emptyText) {
    const prompt = document.createElement('option');
    prompt.value = '';
    prompt.textContent = items.length ? 'Selecciona una opción' : emptyText;
    select.replaceChildren(prompt, ...items.map((item) => {
        const option = document.createElement('option');
        option.value = String(item.id);
        option.textContent = label(item);
        return option;
    }));
    select.disabled = items.length === 0;
}

export function clearErrors(form) {
    form.querySelectorAll('[data-field-error]').forEach((node) => { node.textContent = ''; });
    form.querySelectorAll('[aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
}

export function showErrors(form, errors) {
    let first = null;
    Object.entries(errors ?? {}).forEach(([field, messages]) => {
        const control = [...form.elements].find((element) => element.name === field);
        const output = [...form.querySelectorAll('[data-field-error]')].find((element) => element.dataset.fieldError === field);
        const message = Array.isArray(messages) ? messages[0] : String(messages);
        if (output) output.textContent = message;
        if (control instanceof HTMLElement) {
            control.setAttribute('aria-invalid', 'true');
            if (!first) first = control;
        }
    });
    first?.focus();
    return Boolean(first);
}

export function setNotice(root, text, error = false) {
    const notice = root.querySelector('[data-operation-notice]');
    notice.textContent = text;
    notice.classList.toggle('text-red-800', error);
    notice.classList.toggle('text-gintly-text-primary', !error);
    notice.hidden = false;
    notice.focus();
}

export function makeField({ name, label, type = 'text', options: choices = null, required = true, value = '' }) {
    const wrapper = document.createElement('div');
    const caption = document.createElement('label');
    const control = choices ? document.createElement('select') : document.createElement('input');
    const error = document.createElement('p');
    caption.className = 'block text-sm font-semibold text-gintly-text-primary';
    caption.textContent = label;
    control.name = name;
    control.required = required;
    control.className = 'mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-gintly-brand focus:outline-none focus:ring-2 focus:ring-gintly-brand/20';
    if (choices) options(control, choices, (item) => item.label, 'No hay opciones disponibles');
    else { control.type = type; control.value = value; if (type === 'text') control.inputMode = 'decimal'; }
    error.className = 'mt-1 text-sm text-red-700';
    error.dataset.fieldError = name;
    caption.appendChild(control);
    wrapper.append(caption, error);
    return wrapper;
}
