import { api, ApiError, initializeCsrf } from './api-client';

const HUMAN_ROLES = new Set(['ROL-01', 'ROL-02', 'ROL-03']);

const ROLE_LABELS = Object.freeze({
    'ROL-01': 'Propietario',
    'ROL-02': 'Administrador',
    'ROL-03': 'Operativo',
});

const PROFILE_LABELS = Object.freeze({
    cajero: 'Cajero',
    facturador: 'Facturador',
    bodeguero: 'Bodeguero',
    despachador: 'Despachador',
});

let contextPromise = null;

export class SessionContextError extends Error {
    constructor(message) {
        super(message);
        this.name = 'SessionContextError';
    }
}

function normalizedArray(value, field) {
    if (!Array.isArray(value) || value.some((item) => typeof item !== 'string')) {
        throw new SessionContextError(`El contexto autenticado no contiene ${field} válidas.`);
    }

    return [...new Set(value)];
}

function normalize(payload) {
    const data = payload?.data;

    if (!data || typeof data !== 'object') {
        throw new SessionContextError('El servidor no devolvió un contexto autenticado válido.');
    }

    if (!HUMAN_ROLES.has(data.role)) {
        throw new SessionContextError('La cuenta no dispone de un rol humano válido para el panel.');
    }

    if (typeof data.name !== 'string' || data.name.trim() === '') {
        throw new SessionContextError('El contexto autenticado no contiene una identidad válida.');
    }

    const capabilities = normalizedArray(data.capabilities, 'capacidades');
    const profiles = normalizedArray(data.profiles, 'perfiles');

    if (capabilities.length === 0) {
        throw new SessionContextError('La cuenta no contiene capacidades efectivas para el panel.');
    }

    if (data.role === 'ROL-03' && profiles.length === 0) {
        throw new SessionContextError('La cuenta operativa no contiene perfiles válidos.');
    }

    if (data.role !== 'ROL-03' && profiles.length > 0) {
        throw new SessionContextError('El contexto autenticado contiene perfiles incompatibles con el rol.');
    }

    const branchId = Number.isInteger(data.branch_id)
        ? data.branch_id
        : null;

    return Object.freeze({
        identity: Object.freeze({
            id: data.id,
            name: typeof data.name === 'string' ? data.name.trim() : '',
            email: typeof data.email === 'string' ? data.email.trim() : '',
            isActive: data.is_active === true,
        }),
        role: data.role,
        roleLabel: ROLE_LABELS[data.role],
        profiles: Object.freeze(profiles),
        profileLabels: Object.freeze(profiles.map((profile) => PROFILE_LABELS[profile] ?? profile)),
        capabilities: Object.freeze(capabilities),
        business: Object.freeze({
            name: typeof data.business?.name === 'string' ? data.business.name.trim() : '',
            timezone: typeof data.business?.timezone === 'string' ? data.business.timezone.trim() : '',
            status: typeof data.business?.status === 'string' ? data.business.status.trim() : '',
        }),
        branch: branchId === null
            ? null
            : Object.freeze({ id: branchId }),
    });
}

export function getSessionContext({ refresh = false } = {}) {
    if (refresh) {
        contextPromise = null;
    }

    contextPromise ??= api.get('/me', {}, { dispatchErrors: false })
        .catch(async (error) => {
            if (!(error instanceof ApiError) || error.status !== 419) {
                throw error;
            }

            await initializeCsrf({ dispatchErrors: false });

            return api.get('/me', {}, { dispatchErrors: false });
        })
        .then(normalize);

    return contextPromise;
}
