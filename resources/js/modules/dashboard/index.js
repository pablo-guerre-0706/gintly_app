import { ApiError } from '@/core/api-client';
import { getSessionContext, SessionContextError } from '@/core/session-context';
import { initAdminDashboard } from './admin';
import { initOwnerDashboard } from './owner';

function contextMessage(error) {
    if (error instanceof SessionContextError) return error.message;
    if (error instanceof ApiError) {
        if (error.status === 403) return 'La cuenta no está autorizada para este dashboard.';
        if (error.status === 419) return 'La sesión de seguridad expiró.';
        if (error.status === 0) return 'No fue posible conectar con el servidor.';
        if (error.status >= 500) return 'El servidor no pudo cargar el dashboard.';
    }
    return error?.message || 'No fue posible validar el dashboard.';
}

function renderGateError(gate, message) {
    const title = document.createElement('h1'); const body = document.createElement('p'); const retry = document.createElement('button');
    gate.hidden = false; gate.replaceChildren(); gate.className = 'rounded-2xl border border-red-200 bg-red-50 p-6 text-red-900'; gate.setAttribute('role', 'alert');
    title.className = 'text-lg font-semibold'; title.textContent = 'Dashboard no disponible';
    body.className = 'mt-2 text-sm'; body.textContent = message;
    retry.type = 'button'; retry.className = 'mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold'; retry.textContent = 'Reintentar';
    retry.addEventListener('click', () => window.location.reload()); gate.append(title, body, retry);
}

export default async function init() {
    const gate = document.querySelector('[data-dashboard-router-gate]');
    if (!gate || gate.dataset.initialized === 'true') return;
    gate.dataset.initialized = 'true';
    try {
        const context = await getSessionContext();
        let initialization;
        if (context.role === 'ROL-01') initialization = initOwnerDashboard(context);
        else if (context.role === 'ROL-02') initialization = initAdminDashboard(context);
        else throw new SessionContextError('El dashboard operativo ROL-03 se implementará en su fase específica.');
        gate.hidden = true;
        await initialization;
    } catch (error) { renderGateError(gate, contextMessage(error)); }
}
