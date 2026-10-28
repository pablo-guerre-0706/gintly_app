import { api, ApiError } from '@/core/api-client';
import { escapeHtml } from '@/core/dom';
import { clearFormErrors, formErrorMessage, showFormErrors, submitFormOnce, trackUnsaved } from '@/core/form-ui';
import { money } from '@/core/money';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';
import { getSessionContext } from '@/core/session-context';

const state = {
    page: 1,
    lastPage: 1,
    search: '',
    active: '',
    records: new Map(),
    selected: null,
    canManage: false,
    listRequest: null,
    detailRequest: null,
    pending: new Set(),
    searchTimer: null,
    clearDirty: null,
    confirmResolver: null,
    confirmOpener: null,
};

const confirmScrollOwner = Symbol('customer-confirm');
const esc = (value, fallback = '—') => escapeHtml(value, fallback);

function currency(value) {
    try {
        return `C$ ${money(String(value ?? '0')).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
    } catch {
        return 'C$ 0.00';
    }
}

function date(value) {
    if (!value) return 'No registrada';
    const parsed = new Date(`${value}T00:00:00`);
    return Number.isNaN(parsed.getTime())
        ? 'No registrada'
        : new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium' }).format(parsed);
}

function statusClasses(isActive) {
    return isActive
        ? 'bg-emerald-100 text-emerald-800'
        : 'bg-slate-200 text-slate-700';
}

function documentLabel(customer) {
    const type = customer.document_type_label || 'Identificación';
    return customer.document_number ? `${type}: ${customer.document_number}` : `${type}: no registrada`;
}

function cardTemplate(customer) {
    const active = customer.is_active === true;
    const selected = Number(customer.id) === Number(state.selected?.id);
    const management = state.canManage
        ? `<button type="button" data-customer-action="edit" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold">Editar</button>
           <button type="button" data-customer-action="toggle" class="min-h-10 rounded-lg border px-3 text-sm font-bold ${active ? 'border-amber-300 text-amber-800' : 'border-emerald-300 text-emerald-800'}">${active ? 'Desactivar' : 'Reactivar'}</button>`
        : '';

    return `<article data-customer-id="${Number(customer.id)}" class="min-w-0 rounded-2xl border bg-white p-5 transition ${selected ? 'border-gintly-brand ring-2 ring-gintly-brand/15' : 'border-slate-200 hover:border-slate-300'}">
        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h3 class="break-words text-base font-bold text-gintly-text-primary">${esc(customer.name)}</h3>
                <p class="mt-1 break-words text-sm text-gintly-text-secondary">${esc(documentLabel(customer))}</p>
            </div>
            <span class="inline-flex w-fit shrink-0 rounded-full px-3 py-1 text-xs font-bold ${statusClasses(active)}">${active ? 'Activo' : 'Inactivo'}</span>
        </div>
        <dl class="mt-5 grid min-w-0 gap-4 sm:grid-cols-2">
            <div class="min-w-0"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Teléfono</dt><dd class="mt-1 break-words text-sm">${esc(customer.phone_number)}</dd></div>
            <div class="min-w-0"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Correo</dt><dd class="mt-1 break-words text-sm">${esc(customer.email)}</dd></div>
            <div class="min-w-0 sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Límite de crédito</dt><dd class="mt-1 text-base font-bold">${currency(customer.credit_limit)}</dd></div>
        </dl>
        <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <button type="button" data-customer-action="view" class="min-h-10 rounded-lg bg-gintly-brand px-3 text-sm font-bold text-white">Ver</button>
            ${management}
        </div>
    </article>`;
}

function listMessage(error) {
    if (!(error instanceof ApiError)) return 'No fue posible consultar los clientes.';
    if (error.status === 403) return 'No tienes autorización para consultar clientes.';
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo cargar los clientes.';
    return error.message || 'No fue posible consultar los clientes.';
}

function renderState(root, message, { error = false, retry = false } = {}) {
    const target = root.querySelector('[data-customers-state]');
    target.innerHTML = `<p class="${error ? 'font-semibold text-red-700' : ''}">${esc(message)}</p>
        ${retry ? '<button type="button" data-customers-retry class="mt-4 min-h-11 rounded-xl border border-red-300 px-4 text-sm font-semibold text-red-800">Reintentar</button>' : ''}`;
    target.hidden = false;
}

function renderCustomers(root, response) {
    if (!Array.isArray(response?.data) || !response?.meta) {
        throw new TypeError('El servidor no devolvió un listado paginado de clientes.');
    }
    const customers = response.data;
    state.records = new Map(customers.map((customer) => [Number(customer.id), customer]));
    root.querySelector('[data-customers-list]').innerHTML = customers.map(cardTemplate).join('');
    const total = Number(response.meta.total ?? customers.length);
    root.querySelector('[data-customers-summary]').textContent = `${total} cliente${total === 1 ? '' : 's'}`;
    if (customers.length === 0) renderState(root, 'No hay clientes para la búsqueda y el estado seleccionados.');
    else root.querySelector('[data-customers-state]').hidden = true;
    state.page = Number(response.meta.current_page ?? 1);
    state.lastPage = Number(response.meta.last_page ?? 1);
    const pagination = root.querySelector('[data-customers-pagination]');
    pagination.hidden = state.lastPage <= 1;
    pagination.querySelector('[data-customers-page]').textContent = `Página ${state.page} de ${state.lastPage}`;
    pagination.querySelector('[data-customers-previous]').disabled = state.page <= 1;
    pagination.querySelector('[data-customers-next]').disabled = state.page >= state.lastPage;
}

function markSelected(root, id) {
    root.querySelectorAll('[data-customer-id]').forEach((card) => {
        const selected = Number(card.dataset.customerId) === id;
        card.classList.toggle('border-gintly-brand', selected);
        card.classList.toggle('ring-2', selected);
        card.classList.toggle('ring-gintly-brand/15', selected);
        card.classList.toggle('border-slate-200', !selected);
    });
}

function detailTemplate(customer) {
    const active = customer.is_active === true;
    const management = state.canManage
        ? `<div class="mt-6 flex flex-wrap gap-3">
                <button type="button" data-customer-detail-action="edit" class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold">Editar</button>
                <button type="button" data-customer-detail-action="toggle" class="min-h-11 rounded-xl border px-4 text-sm font-bold ${active ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900'}">${active ? 'Desactivar' : 'Reactivar'}</button>
           </div>`
        : '';
    return `<article class="min-w-0" data-customer-detail-view>
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-semibold uppercase tracking-wide text-gintly-brand">Detalle del cliente</p>
                <h2 class="mt-2 break-words text-xl font-bold">${esc(customer.name)}</h2>
                <p class="mt-2 break-words text-sm text-gintly-text-secondary">${esc(documentLabel(customer))}</p>
            </div>
            <span class="inline-flex w-fit shrink-0 rounded-full px-3 py-1 text-xs font-bold ${statusClasses(active)}">${active ? 'Activo' : 'Inactivo'}</span>
        </div>
        <dl class="mt-6 grid gap-5 sm:grid-cols-2">
            <div class="min-w-0"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Teléfono</dt><dd class="mt-1 break-words text-sm font-semibold">${esc(customer.phone_number, 'No registrado')}</dd></div>
            <div class="min-w-0"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Correo</dt><dd class="mt-1 break-words text-sm font-semibold">${esc(customer.email, 'No registrado')}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Límite de crédito</dt><dd class="mt-1 text-lg font-bold">${currency(customer.credit_limit)}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha de nacimiento</dt><dd class="mt-1 text-sm font-semibold">${date(customer.birth_date)}</dd></div>
        </dl>
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-bold">Observaciones</h3>
            <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-gintly-text-secondary">${esc(customer.notes, 'Sin observaciones registradas.')}</p>
        </div>
        ${management}
    </article>`;
}

function renderDetail(root, customer) {
    state.selected = customer;
    root.querySelector('[data-customer-detail]').innerHTML = detailTemplate(customer);
    markSelected(root, Number(customer.id));
}

function editTemplate(customer) {
    return `<form data-customer-edit-form novalidate class="space-y-5" aria-labelledby="customer-edit-title">
        <div class="border-b border-slate-200 pb-5">
            <p class="text-sm font-semibold uppercase tracking-wide text-gintly-brand">Clientes</p>
            <h2 id="customer-edit-title" class="mt-2 text-xl font-bold">Editar cliente</h2>
            <p class="mt-2 text-sm leading-6 text-gintly-text-secondary">El estado se administra por separado para exigir confirmación explícita.</p>
        </div>
        <p data-form-error-summary role="alert" tabindex="-1" hidden class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800"></p>
        <div><label for="customer-edit-name" class="block text-sm font-semibold">Nombre completo</label><input id="customer-edit-name" name="name" required maxlength="160" value="${esc(customer.name, '')}" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm"><p data-error-for="name" class="mt-1 text-sm text-red-700"></p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="customer-edit-type" class="block text-sm font-semibold">Tipo de documento</label><select id="customer-edit-type" name="document_type" required class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="cedula" ${customer.document_type === 'cedula' ? 'selected' : ''}>Cédula</option><option value="ruc" ${customer.document_type === 'ruc' ? 'selected' : ''}>RUC</option><option value="pasaporte" ${customer.document_type === 'pasaporte' ? 'selected' : ''}>Pasaporte</option></select><p data-error-for="document_type" class="mt-1 text-sm text-red-700"></p></div>
            <div><label for="customer-edit-document" class="block text-sm font-semibold">Identificación</label><input id="customer-edit-document" name="document_number" maxlength="30" value="${esc(customer.document_number, '')}" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm"><p data-error-for="document_number" class="mt-1 text-sm text-red-700"></p></div>
            <div><label for="customer-edit-phone" class="block text-sm font-semibold">Teléfono</label><input id="customer-edit-phone" name="phone_number" type="tel" maxlength="30" value="${esc(customer.phone_number, '')}" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm"><p data-error-for="phone_number" class="mt-1 text-sm text-red-700"></p></div>
            <div><label for="customer-edit-email" class="block text-sm font-semibold">Correo</label><input id="customer-edit-email" name="email" type="email" maxlength="180" value="${esc(customer.email, '')}" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm"><p data-error-for="email" class="mt-1 text-sm text-red-700"></p></div>
            <div><label for="customer-edit-birth" class="block text-sm font-semibold">Fecha de nacimiento</label><input id="customer-edit-birth" name="birth_date" type="date" value="${esc(customer.birth_date, '')}" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm"><p data-error-for="birth_date" class="mt-1 text-sm text-red-700"></p></div>
            <div><label for="customer-edit-credit" class="block text-sm font-semibold">Límite de crédito</label><input id="customer-edit-credit" name="credit_limit" inputmode="decimal" value="${esc(customer.credit_limit, '0.00')}" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm"><p data-error-for="credit_limit" class="mt-1 text-sm text-red-700"></p></div>
        </div>
        <div><label for="customer-edit-notes" class="block text-sm font-semibold">Observaciones</label><textarea id="customer-edit-notes" name="notes" rows="3" maxlength="500" class="mt-2 w-full rounded-xl border border-slate-300 p-4 text-sm">${esc(customer.notes, '')}</textarea><p data-error-for="notes" class="mt-1 text-sm text-red-700"></p></div>
        <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-5">
            <button type="submit" data-customer-edit-save class="min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-bold text-white disabled:opacity-60">Guardar cambios</button>
            <button type="button" data-customer-edit-cancel class="min-h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold">Cancelar</button>
        </div>
    </form>`;
}

async function loadCustomers(root) {
    state.listRequest?.abort();
    const controller = new AbortController();
    state.listRequest = controller;
    root.setAttribute('aria-busy', 'true');
    root.querySelector('[data-customers-list]').replaceChildren();
    root.querySelector('[data-customers-pagination]').hidden = true;
    renderState(root, 'Cargando clientes…');
    try {
        const response = await api.get(root.dataset.customersEndpoint, {
            page: state.page,
            per_page: 25,
            search: state.search || undefined,
            is_active: state.active === '' ? undefined : state.active,
            sort: 'name',
            direction: 'asc',
        }, { signal: controller.signal, dispatchErrors: false });
        if (!controller.signal.aborted) renderCustomers(root, response);
    } catch (error) {
        if (controller.signal.aborted) return;
        root.querySelector('[data-customers-summary]').textContent = 'Consulta no disponible';
        renderState(root, listMessage(error), { error: true, retry: true });
    } finally {
        if (state.listRequest === controller) state.listRequest = null;
        root.setAttribute('aria-busy', 'false');
    }
}

async function openDetail(root, id) {
    if (!Number.isInteger(id) || id < 1) return null;
    state.detailRequest?.abort();
    const controller = new AbortController();
    state.detailRequest = controller;
    markSelected(root, id);
    root.querySelector('[data-customer-detail]').innerHTML = '<p class="min-h-80 py-12 text-center text-sm text-gintly-text-secondary" role="status">Cargando detalle del cliente…</p>';
    try {
        const response = await api.get(`${root.dataset.customersEndpoint}/${id}`, {}, { signal: controller.signal, dispatchErrors: false });
        if (controller.signal.aborted) return null;
        if (Number(response?.data?.id) !== id) throw new TypeError('El servidor no devolvió el cliente solicitado.');
        renderDetail(root, response.data);
        return response.data;
    } catch (error) {
        if (controller.signal.aborted) return null;
        root.querySelector('[data-customer-detail]').innerHTML = `<div class="min-h-80 py-12 text-center" role="alert"><p class="text-sm font-semibold text-red-700">${esc(listMessage(error))}</p><button type="button" data-customer-detail-retry class="mt-4 min-h-11 rounded-xl border border-red-300 px-4 text-sm font-semibold text-red-800">Reintentar</button></div>`;
        return null;
    } finally {
        if (state.detailRequest === controller) state.detailRequest = null;
    }
}

function notify(root, message, error = false) {
    const notice = root.querySelector('[data-customers-notice]');
    notice.textContent = message;
    notice.className = `rounded-xl border p-4 text-sm font-semibold ${error ? 'border-red-200 bg-red-50 text-red-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}`;
    notice.hidden = false;
    notice.focus();
}

function confirmAction(root, message, label) {
    const dialog = root.querySelector('[data-customer-confirm]');
    if (dialog.open) return Promise.resolve(false);
    state.confirmOpener = document.activeElement;
    dialog.querySelector('[data-customer-confirm-message]').textContent = message;
    dialog.querySelector('[data-customer-confirm-accept]').textContent = label;
    dialog.returnValue = 'cancel';
    lockScroll(confirmScrollOwner);
    dialog.showModal();
    dialog.querySelector('[data-customer-confirm-cancel]').focus();
    return new Promise((resolve) => { state.confirmResolver = resolve; });
}

async function toggleCustomer(root, id) {
    const customer = state.records.get(id) ?? (Number(state.selected?.id) === id ? state.selected : null);
    if (!state.canManage || !customer || state.pending.has(id)) return;
    const activating = customer.is_active !== true;
    const confirmed = await confirmAction(root,
        activating
            ? `¿Reactivar a «${customer.name}»? Volverá a estar disponible para las operaciones autorizadas.`
            : `¿Desactivar a «${customer.name}»? El registro, historial y relaciones se conservarán; la acción se rechazará si tiene cuentas por cobrar vivas.`,
        activating ? 'Reactivar' : 'Desactivar');
    if (!confirmed) return;

    state.pending.add(id);
    root.querySelectorAll(`[data-customer-id="${id}"] button, [data-customer-detail] button`).forEach((button) => { button.disabled = true; });
    try {
        const response = await api.put(`${root.dataset.customersEndpoint}/${id}`, { is_active: activating }, { dispatchErrors: false, expectedStatus: 200 });
        if (Number(response?.data?.id) !== id || response.data.is_active !== activating) throw new TypeError('El servidor no confirmó el nuevo estado.');
        renderDetail(root, response.data);
        notify(root, `Cliente «${response.data.name}» ${activating ? 'reactivado' : 'desactivado'} correctamente.`);
        await loadCustomers(root);
    } catch (error) {
        const message = error instanceof ApiError && error.code === 'CUSTOMER_HAS_RECEIVABLES'
            ? 'No se desactivó el cliente porque conserva cuentas por cobrar pendientes, parciales o vencidas.'
            : formErrorMessage(error);
        notify(root, message, true);
    } finally {
        state.pending.delete(id);
        root.querySelectorAll(`[data-customer-id="${id}"] button, [data-customer-detail] button`).forEach((button) => { button.disabled = false; });
    }
}

function openEdit(root, customer) {
    if (!state.canManage || !customer) return;
    const detail = root.querySelector('[data-customer-detail]');
    detail.innerHTML = editTemplate(customer);
    const form = detail.querySelector('[data-customer-edit-form]');
    clearFormErrors(form);
    state.clearDirty?.();
    state.clearDirty = trackUnsaved(form);
    form.elements.name.focus();
}

async function requestEdit(root, id) {
    let customer = Number(state.selected?.id) === id ? state.selected : null;
    if (!customer) customer = await openDetail(root, id);
    if (customer) openEdit(root, customer);
}

async function cancelEdit(root) {
    const form = root.querySelector('[data-customer-edit-form]');
    if (!form || form.dataset.submitting === 'true') return;
    if (state.clearDirty?.isDirty() && !await confirmAction(root, '¿Descartar los cambios sin guardar?', 'Descartar cambios')) return;
    state.clearDirty?.();
    state.clearDirty = null;
    if (state.selected) renderDetail(root, state.selected);
}

function editPayload(form) {
    const data = Object.fromEntries(new FormData(form).entries());
    data.name = data.name.trim();
    data.document_number = data.document_number.trim() || null;
    data.phone_number = data.phone_number.trim() || null;
    data.email = data.email.trim() || null;
    data.birth_date = data.birth_date || null;
    data.credit_limit = money(data.credit_limit.trim() || '0');
    data.notes = data.notes.trim() || null;
    return data;
}

function validateEdit(form) {
    const errors = {};
    if (!form.elements.name.value.trim()) errors.name = ['El nombre es obligatorio.'];
    const credit = form.elements.credit_limit.value.trim();
    if (credit && !/^\d+(?:\.\d{1,2})?$/.test(credit)) errors.credit_limit = ['Usa un importe no negativo con máximo dos decimales.'];
    if (!form.checkValidity()) {
        for (const field of form.elements) {
            if (field instanceof HTMLElement && field.name && !field.validity.valid) errors[field.name] = [field.validationMessage];
        }
    }
    return errors;
}

async function saveEdit(root, form) {
    const id = Number(state.selected?.id);
    if (!state.canManage || !Number.isInteger(id)) return;
    const errors = validateEdit(form);
    if (Object.keys(errors).length) {
        showFormErrors(form, errors);
        return;
    }
    await submitFormOnce(form, form.querySelector('[data-customer-edit-save]'),
        () => api.put(`${root.dataset.customersEndpoint}/${id}`, editPayload(form), { dispatchErrors: false, expectedStatus: 200 }), {
            onSuccess: async (response) => {
                if (Number(response?.data?.id) !== id) throw new TypeError('El servidor no confirmó la actualización.');
                state.clearDirty?.();
                state.clearDirty = null;
                renderDetail(root, response.data);
                notify(root, `Cliente «${response.data.name}» actualizado correctamente.`);
                await loadCustomers(root);
            },
        });
}

async function authorizeAndLoad(root) {
    try {
        const context = await getSessionContext();
        if (!context.capabilities.includes('clientes.ver')) {
            renderState(root, 'No tienes autorización para consultar clientes.', { error: true });
            root.setAttribute('aria-busy', 'false');
            return;
        }
        state.canManage = ['ROL-01', 'ROL-02'].includes(context.role) && context.capabilities.includes('clientes.gestionar');
        root.querySelector('[data-create-customer]').hidden = !['ROL-01', 'ROL-02', 'ROL-03'].includes(context.role);
        await loadCustomers(root);
    } catch (error) {
        renderState(root, listMessage(error), { error: true, retry: true });
        root.setAttribute('aria-busy', 'false');
    }
}

export default function init() {
    const root = document.querySelector('[data-customers-root]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    const filters = root.querySelector('[data-customers-filters]');
    const searchError = root.querySelector('[data-customers-search-error]');
    const confirm = root.querySelector('[data-customer-confirm]');
    confirm.querySelector('[data-customer-confirm-cancel]').addEventListener('click', () => confirm.close('cancel'));
    confirm.querySelector('[data-customer-confirm-accept]').addEventListener('click', () => confirm.close('confirm'));
    confirm.addEventListener('close', () => {
        unlockScroll(confirmScrollOwner);
        state.confirmOpener?.focus();
        state.confirmOpener = null;
        state.confirmResolver?.(confirm.returnValue === 'confirm');
        state.confirmResolver = null;
    });

    filters.addEventListener('submit', (event) => {
        event.preventDefault();
        const query = filters.elements.search.value.trim();
        if (query.length === 1) {
            searchError.textContent = 'Escribe al menos 2 caracteres para buscar.';
            searchError.hidden = false;
            filters.elements.search.focus();
            return;
        }
        searchError.hidden = true;
        state.search = query;
        state.active = filters.elements.is_active.value;
        state.page = 1;
        void loadCustomers(root);
    });
    filters.elements.search.addEventListener('input', () => {
        window.clearTimeout(state.searchTimer);
        const query = filters.elements.search.value.trim();
        if (query.length === 1) return;
        searchError.hidden = true;
        state.searchTimer = window.setTimeout(() => {
            state.search = query;
            state.page = 1;
            void loadCustomers(root);
        }, 350);
    });
    filters.elements.is_active.addEventListener('change', () => {
        state.active = filters.elements.is_active.value;
        state.page = 1;
        void loadCustomers(root);
    });

    root.addEventListener('click', (event) => {
        const action = event.target.closest('[data-customer-action]');
        if (action) {
            const id = Number(action.closest('[data-customer-id]')?.dataset.customerId);
            if (action.dataset.customerAction === 'view') void openDetail(root, id);
            else if (action.dataset.customerAction === 'edit') void requestEdit(root, id);
            else if (action.dataset.customerAction === 'toggle') void toggleCustomer(root, id);
            return;
        }
        const detailAction = event.target.closest('[data-customer-detail-action]');
        if (detailAction && state.selected) {
            if (detailAction.dataset.customerDetailAction === 'edit') openEdit(root, state.selected);
            else void toggleCustomer(root, Number(state.selected.id));
            return;
        }
        if (event.target.closest('[data-customers-retry]')) void authorizeAndLoad(root);
        else if (event.target.closest('[data-customers-previous]')) { state.page = Math.max(1, state.page - 1); void loadCustomers(root); }
        else if (event.target.closest('[data-customers-next]')) { state.page = Math.min(state.lastPage, state.page + 1); void loadCustomers(root); }
        else if (event.target.closest('[data-customer-detail-retry]') && state.selected) void openDetail(root, Number(state.selected.id));
        else if (event.target.closest('[data-customer-edit-cancel]')) void cancelEdit(root);
    });
    root.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-customer-edit-form]');
        if (!form) return;
        event.preventDefault();
        void saveEdit(root, form);
    });
    return authorizeAndLoad(root);
}
