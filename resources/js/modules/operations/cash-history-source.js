const VIEWS = Object.freeze({
    all: { statuses: [null], sort: 'opened_at' },
    openings: { statuses: [null], sort: 'opened_at' },
    closings: { statuses: ['cerrada', 'descuadrada'], sort: 'closed_at' },
    open: { statuses: ['abierta'], sort: 'opened_at' },
    unbalanced: { statuses: ['descuadrada'], sort: 'closed_at' },
});

function validatePage(response) {
    const meta = response?.meta;
    if (!Array.isArray(response?.data) || !Number.isInteger(meta?.current_page)
        || !Number.isInteger(meta?.last_page) || !Number.isInteger(meta?.total)) {
        throw new TypeError('El servidor no devolvió un historial paginado válido.');
    }
    return response;
}

export function historyFilters(entries) {
    const input = Object.fromEntries(entries);
    const view = input.view ?? 'all';
    if (!Object.hasOwn(VIEWS, view)) throw new TypeError('Selecciona una vista válida del historial.');
    const filters = { view };
    // from/to filtran opened_at en el contrato, incluso al consultar cierres.
    for (const key of ['from', 'to', 'cash_register_id', 'opened_by']) {
        if (input[key]) filters[key] = input[key];
    }
    return filters;
}

export class CashHistorySource {
    constructor(read, validateRecords = () => {}, pageSize = 15) {
        this.read = read;
        this.validateRecords = validateRecords;
        this.pageSize = pageSize;
        this.reset();
    }

    reset() { this.signature = null; this.streams = []; }

    async load(filters, page = 1, signal = null) {
        const { view, ...query } = filters;
        const config = VIEWS[view];
        if (!config || !Number.isInteger(page) || page < 1) throw new TypeError('Historial no válido.');
        const base = { ...query, per_page: this.pageSize, sort: config.sort, direction: 'desc' };
        if (config.statuses.length === 1) {
            const status = config.statuses[0];
            const response = validatePage(await this.read('/cash-sessions', { ...base, page, ...(status ? { status } : {}) }, signal));
            this.validateRecords(response.data);
            return response;
        }

        const signature = JSON.stringify(base);
        if (signature !== this.signature) {
            this.signature = signature;
            this.streams = config.statuses.map((status) => ({ status, records: [], page: 0, last: 1, total: 0 }));
        }
        // La API admite un solo estado. Se combinan dos flujos paginados por closed_at.
        // Para obtener los primeros N cierres basta cargar los primeros N de CADA flujo,
        // nunca filtrar únicamente una página mixta ni descargar todo el historial.
        await Promise.all(this.streams.map(async (stream) => {
            while (stream.page < page && stream.page < stream.last) {
                const response = validatePage(await this.read('/cash-sessions', { ...base, status: stream.status, page: stream.page + 1 }, signal));
                this.validateRecords(response.data);
                if (response.data.some((session) => session.status !== stream.status || !session.closed_at)) {
                    throw new TypeError('El servidor devolvió un registro que no corresponde a los cierres solicitados.');
                }
                stream.records.push(...response.data);
                stream.page = response.meta.current_page;
                stream.last = response.meta.last_page;
                stream.total = response.meta.total;
            }
        }));
        const records = this.streams.flatMap((stream) => stream.records)
            .sort((a, b) => Date.parse(b.closed_at) - Date.parse(a.closed_at) || b.id - a.id);
        const total = this.streams.reduce((sum, stream) => sum + stream.total, 0);
        const last = Math.max(1, Math.ceil(total / this.pageSize));
        const current = Math.min(page, last);
        return { data: records.slice((current - 1) * this.pageSize, current * this.pageSize), meta: { current_page: current, last_page: last, total } };
    }
}
