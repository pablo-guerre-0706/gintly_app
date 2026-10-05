import { ApiError, initializeCsrf } from './api-client';

export function clearFormErrors(form) {
    form.querySelectorAll('[aria-invalid="true"]').forEach((field) => field.removeAttribute('aria-invalid'));
    form.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
    const summary = form.querySelector('[data-form-error-summary]');
    if (summary) { summary.textContent = ''; summary.hidden = true; }
}

export function showFormErrors(form, errors, fallback = 'Revisa los campos indicados.') {
    clearFormErrors(form);
    let first = null;
    const summary = form.querySelector('[data-form-error-summary]');
    for (const [name, values] of Object.entries(errors ?? {})) {
        const key = name.split('.')[0];
        const field = form.elements.namedItem(key);
        const output = [...form.querySelectorAll('[data-error-for]')].find((node) => node.dataset.errorFor === key);
        const message = Array.isArray(values) ? values[0] : values;
        if (field instanceof HTMLElement) {
            field.setAttribute('aria-invalid', 'true');
            first ??= field;
        }
        if (output) output.textContent = String(message ?? fallback);
    }
    if (summary) { summary.textContent = fallback; summary.hidden = false; }
    (first ?? summary)?.focus();
}

export function formErrorMessage(error) {
    if (!(error instanceof ApiError)) return 'No hay conexión o la respuesta es incierta. Conservamos el formulario; verifica el resultado antes de reintentar.';
    if (error.status === 401) return 'La sesión expiró. Inicia sesión nuevamente.';
    if (error.status === 403) return 'No tienes autorización para esta acción.';
    if (error.status === 404) return 'El recurso ya no está disponible.';
    if (error.status === 409) return error.message || 'Los datos entran en conflicto con el estado actual.';
    if (error.status === 419) return 'La protección de la sesión expiró. Recarga la página.';
    if (error.status === 422) return error.message || 'Revisa los campos indicados.';
    if (error.status === 429) return 'Se alcanzó el límite temporal. Espera antes de reintentar.';
    if (error.status >= 500 || error.status === 0) return 'No se pudo confirmar el resultado. Verifica si la operación se registró antes de reintentar.';
    return error.message || 'No se pudo completar la operación.';
}

export async function submitFormOnce(form, button, send, { onSuccess, onError } = {}) {
    if (form.dataset.submitting === 'true') return;
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    button.disabled = true;
    clearFormErrors(form);
    try {
        let result;
        try { result = await send(); }
        catch (error) {
            if (!(error instanceof ApiError) || error.status !== 419) throw error;
            await initializeCsrf({ dispatchErrors: false });
            result = await send();
        }
        await onSuccess?.(result);
        return result;
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) showFormErrors(form, error.errors, formErrorMessage(error));
        else {
            const summary = form.querySelector('[data-form-error-summary]');
            if (summary) { summary.textContent = formErrorMessage(error); summary.hidden = false; summary.focus(); }
        }
        await onError?.(error);
        return null;
    } finally {
        button.disabled = false;
        form.setAttribute('aria-busy', 'false');
        form.dataset.submitting = 'false';
    }
}

export function trackUnsaved(form) {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    const warn = (event) => {
        if (!dirty || form.dataset.submitting === 'true') return;
        event.preventDefault();
        event.returnValue = '';
    };
    window.addEventListener('beforeunload', warn);
    const stop = () => { dirty = false; window.removeEventListener('beforeunload', warn); };
    stop.isDirty = () => dirty;
    return stop;
}
