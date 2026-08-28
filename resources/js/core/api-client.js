import $ from 'jquery';

const meta = (name) =>
    document.querySelector(meta[name="${name}"])?.content?.trim() || null;

const kindFromStatus = (status) => {
    if (status === 0) return 'network';
    if (status === 401) return 'unauthenticated';
    if (status === 403) return 'forbidden';
    if (status === 409) return 'conflict';
    if (status === 419) return 'csrf';
    if (status === 422) return 'validation';
    if (status === 429) return 'throttled';
    if (status >= 500) return 'server';

    return 'http';
};

const defaultMessage = (status) => ({
    0: 'No fue posible conectar con el servidor.',
    401: 'La sesión ha expirado.',
    403: 'No tiene autorización para realizar esta operación.',
    409: 'La operación presenta un conflicto de negocio.',
    419: 'La sesión de seguridad ha expirado.',
    422: 'Los datos enviados requieren corrección.',
    429: 'Se alcanzó el límite temporal de solicitudes.',
    500: 'Ocurrió un error interno del servidor.',
}[status] ?? 'No fue posible completar la solicitud.');

export class ApiError extends Error {
    constructor({ status, code, message, errors, payload }) {
        super(message);

        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.kind = kindFromStatus(status);
        this.errors = errors;
        this.payload = payload;
    }

    is(status, code = null) {
        return (
            this.status === status &&
            (code === null || this.code === code)
        );
    }
}

function apiUrl(path) {
    const base = new URL(
        meta('api-base-url') ?? '/api/v1/',
        window.location.origin,
    );

    const normalizedBase = base.href.endsWith('/')
        ? base.href
        : `${base.href}/`;

    return new URL(
        path.replace(/^\/+/, ''),
        normalizedBase,
    ).toString();
}

function responsePayload(xhr) {
    if (xhr.responseJSON && typeof xhr.responseJSON === 'object') {
        return xhr.responseJSON;
    }

    try {
        return JSON.parse(xhr.responseText || '{}');
    } catch {
        return {};
    }
}

function normalizeError(xhr) {
    const payload = responsePayload(xhr);

    return new ApiError({
        status: xhr.status ?? 0,
        code:
            payload.code ??
            payload.error?.code ??
            null,
        message:
            payload.message ??
            defaultMessage(xhr.status ?? 0),
        errors:
            payload.errors ??
            {},
        payload,
    });
}

export function request(
    path,
    {
        method = 'GET',
        data = null,
        headers = {},
        timeout = 30000,
        redirectOn401 = true,
    } = {},
) {
    const verb = method.toUpperCase();
    const hasBody = !['GET', 'HEAD'].includes(verb);
    const csrf = meta('csrf-token');

    let ajaxData = data;
    let processData = true;
    let contentType;

    if (hasBody && data instanceof FormData) {
        processData = false;
        contentType = false;
    } else if (hasBody && data !== null) {
        ajaxData = JSON.stringify(data);
        processData = false;
        contentType = 'application/json; charset=UTF-8';
    }

    return new Promise((resolve, reject) => {
        $.ajax({
            url: apiUrl(path),
            method: verb,
            data: ajaxData ?? undefined,
            processData,
            contentType,
            timeout,

            xhrFields: {
                withCredentials: true,
            },

            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',

                ...(csrf && hasBody
                    ? { 'X-CSRF-TOKEN': csrf }
                    : {}),

                ...headers,
            },
        })
            .done((payload) => {
                resolve(payload ?? null);
            })
            .fail((xhr) => {
                const error = normalizeError(xhr);

                if (error.status === 401 && redirectOn401) {
                    const loginUrl = meta('login-url');

                    if (loginUrl) {
                        window.location.assign(loginUrl);
                    }
                }

                if (error.kind === 'server') {
                    console.error('[Gintly API]', error);
                }

                document.dispatchEvent(
                    new CustomEvent('gintly:http-error', {
                        detail: error,
                    }),
                );

                reject(error);
            });
    });
}

export const api = Object.freeze({
    get: (path, query = {}, options = {}) =>
        request(path, {
            ...options,
            method: 'GET',
            data: query,
        }),

    post: (path, data = {}, options = {}) =>
        request(path, {
            ...options,
            method: 'POST',
            data,
        }),

    put: (path, data = {}, options = {}) =>
        request(path, {
            ...options,
            method: 'PUT',
            data,
        }),

    patch: (path, data = {}, options = {}) =>
        request(path, {
            ...options,
            method: 'PATCH',
            data,
        }),

    delete: (path, data = null, options = {}) =>
        request(path, {
            ...options,
            method: 'DELETE',
            data,
        }),
});
