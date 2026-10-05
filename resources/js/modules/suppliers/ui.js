import { ApiError } from '@/core/api-client';
import { formErrorMessage } from '@/core/form-ui';

export const el = (tag, content = '', classes = '') => {
    const element = document.createElement(tag);
    element.textContent = content; element.className = classes; return element;
};
export const button = (label, action, classes = '') => {
    const element = el('button', label, `min-h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold ${classes}`);
    element.type = 'button'; element.dataset.supplierAction = action; return element;
};
export const message = (error) => error instanceof TypeError ? error.message : formErrorMessage(error);
export function notice(root, value, error = false) {
    const output = root.querySelector('[data-supplier-notice]');
    output.textContent = value; output.hidden = false; output.setAttribute('role', error ? 'alert' : 'status');
}
export function isAbort(error) { return error instanceof ApiError && error.code === 'request_aborted'; }
export function date(value) {
    if (!value) return 'Sin confirmación';
    const timestamp = new Date(value);
    if (Number.isNaN(timestamp.getTime())) throw new TypeError('La fecha de ubicación no es válida.');
    return new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium', timeStyle: 'short' }).format(timestamp);
}
