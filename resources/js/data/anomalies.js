import { api, ApiError, initializeCsrf } from '@/core/api-client';

const ACTIVE_STATUSES = Object.freeze(['detectada', 'notificada', 'en_revision']);
const SEVERITY_PRIORITY = Object.freeze({
    critica: 0,
    advertencia: 1,
    informativa: 2,
});
const CACHE_TTL = 60_000;

let cachedResult = null;
let cachedAt = 0;
let pendingRequest = null;
let requestController = null;

function normalizePage(payload, status) {
    if (!Array.isArray(payload?.data)) {
        throw new ApiError({
            status: 500,
            code: 'invalid_anomaly_payload',
            message: 'El servidor devolvió una colección de anomalías inválida.',
            payload,
        });
    }

    const total = Number(payload?.meta?.total);

    if (!Number.isInteger(total) || total < 0) {
        throw new ApiError({
            status: 500,
            code: 'invalid_anomaly_pagination',
            message: 'El servidor no devolvió el total paginado de anomalías.',
            payload,
        });
    }

    return Object.freeze({
        status,
        total,
        records: Object.freeze(payload.data),
    });
}

async function fetchStatus(status, signal) {
    const options = {
        dispatchErrors: false,
        signal,
    };
    const query = {
        status,
        per_page: 5,
        sort: 'detected_at',
        direction: 'desc',
    };

    try {
        return normalizePage(await api.get('/anomalies', query, options), status);
    } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal.aborted) {
            throw error;
        }

        await initializeCsrf({ dispatchErrors: false });

        return normalizePage(await api.get('/anomalies', query, options), status);
    }
}

function prioritize(records) {
    const unique = new Map();

    records.forEach((record) => {
        if (record && record.id !== undefined && !unique.has(String(record.id))) {
            unique.set(String(record.id), record);
        }
    });

    return [...unique.values()].sort((left, right) => {
        const severity = (SEVERITY_PRIORITY[left.severity] ?? 99)
            - (SEVERITY_PRIORITY[right.severity] ?? 99);

        if (severity !== 0) return severity;

        return new Date(right.detected_at ?? 0).getTime()
            - new Date(left.detected_at ?? 0).getTime();
    });
}

export function getActiveAnomalies({ force = false } = {}) {
    const cacheIsFresh = cachedResult && Date.now() - cachedAt < CACHE_TTL;

    if (!force && cacheIsFresh) return Promise.resolve(cachedResult);
    if (pendingRequest) return pendingRequest;

    requestController = new AbortController();
    const controller = requestController;

    const request = Promise.all(
        ACTIVE_STATUSES.map((status) => fetchStatus(status, controller.signal)),
    ).then((pages) => {
        const totals = Object.fromEntries(pages.map((page) => [page.status, page.total]));
        const result = Object.freeze({
            total: pages.reduce((sum, page) => sum + page.total, 0),
            totals: Object.freeze(totals),
            records: Object.freeze(
                prioritize(pages.flatMap((page) => page.records)).slice(0, 5),
            ),
            fetchedAt: new Date().toISOString(),
        });

        cachedResult = result;
        cachedAt = Date.now();

        return result;
    }).finally(() => {
        if (requestController === controller) requestController = null;
        if (pendingRequest === request) pendingRequest = null;
    });

    pendingRequest = request;

    return pendingRequest;
}

export function cancelActiveAnomaliesRequest() {
    requestController?.abort();
}

export function invalidateActiveAnomalies() {
    requestController?.abort();
    requestController = null;
    pendingRequest = null;
    cachedResult = null;
    cachedAt = 0;
}
