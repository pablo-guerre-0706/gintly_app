const meta = (name) =>
    document.querySelector(`meta[name="${name}"]`)?.content?.trim() || null;

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
    constructor({ status, code = null, message, errors = {}, payload = null }) {
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
    );
}

function csrfUrl() {
    return new URL('/sanctum/csrf-cookie', window.location.origin);
}

function cookie(name) {
    const prefix = `${encodeURIComponent(name)}=`;
    const value = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(prefix))
        ?.slice(prefix.length);

    if (!value) {
        return null;
    }

    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

function appendQuery(url, query) {
    Object.entries(query ?? {}).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        if (Array.isArray(value)) {
            value.forEach((item) => url.searchParams.append(key, String(item)));
            return;
        }

        url.searchParams.set(key, String(value));
    });
}

async function responsePayload(response) {
    if (response.status === 204 || response.status === 205) {
        return null;
    }

    const text = await response.text();

    if (!text) {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch {
        return text;
    }
}

function normalizeResponseError(response, payload) {
    const data = payload && typeof payload === 'object'
        ? payload
        : {};

    return new ApiError({
        status: response.status,
        code: data.code ?? data.error?.code ?? null,
        message: data.message ?? defaultMessage(response.status),
        errors: data.errors ?? {},
        payload,
    });
}

function networkError(error) {
    const timedOut = error?.name === 'AbortError';

    return new ApiError({
        status: 0,
        message: timedOut
            ? 'La solicitud tardó demasiado tiempo. Intente nuevamente.'
            : defaultMessage(0),
        payload: null,
    });
}

function dispatchError(error) {
    if (error.kind === 'server') {
        console.error('[Gintly API]', error);
    }

    document.dispatchEvent(
        new CustomEvent('gintly:http-error', {
            detail: error,
        }),
    );
}

async function fetchJson(
    url,
    {
        method = 'GET',
        data = null,
        headers = {},
        timeout = 30000,
    } = {},
) {
    const verb = method.toUpperCase();
    const hasBody = !['GET', 'HEAD'].includes(verb) && data !== null;
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeout);
    const requestHeaders = new Headers({
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...headers,
    });
    const xsrfToken = cookie('XSRF-TOKEN') ?? meta('csrf-token');

    if (!['GET', 'HEAD', 'OPTIONS'].includes(verb) && xsrfToken) {
        requestHeaders.set('X-XSRF-TOKEN', xsrfToken);
    }

    let body;

    if (hasBody && data instanceof FormData) {
        body = data;
    } else if (hasBody) {
        requestHeaders.set('Content-Type', 'application/json');
        body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, {
            method: verb,
            headers: requestHeaders,
            body,
            credentials: 'same-origin',
            signal: controller.signal,
        });
        const payload = await responsePayload(response);

        if (!response.ok) {
            throw normalizeResponseError(response, payload);
        }

        return payload;
    } catch (error) {
        if (error instanceof ApiError) {
            throw error;
        }

        throw networkError(error);
    } finally {
        window.clearTimeout(timer);
    }
}

export async function initializeCsrf({ timeout = 30000 } = {}) {
    try {
        return await fetchJson(csrfUrl(), { timeout });
    } catch (error) {
        const normalized = error instanceof ApiError
            ? error
            : networkError(error);

        dispatchError(normalized);
        throw normalized;
    }
}

export async function request(
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
    const url = apiUrl(path);

    if (['GET', 'HEAD'].includes(verb)) {
        appendQuery(url, data);
    }

    try {
        return await fetchJson(url, {
            method: verb,
            data: ['GET', 'HEAD'].includes(verb) ? null : data,
            headers,
            timeout,
        });
    } catch (error) {
        const normalized = error instanceof ApiError
            ? error
            : networkError(error);

        if (normalized.status === 401 && redirectOn401) {
            const loginUrl = meta('login-url');

            if (loginUrl) {
                window.location.assign(loginUrl);
            }
        }

        dispatchError(normalized);
        throw normalized;
    }
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
