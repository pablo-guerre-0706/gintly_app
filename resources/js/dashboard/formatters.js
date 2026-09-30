import { decimal, money } from '@/core/money';

function groupedDecimal(value, scale) {
    const normalized = decimal(String(value ?? '0'), scale);
    const negative = normalized.startsWith('-');
    const unsigned = negative ? normalized.slice(1) : normalized;
    const [integer, fraction] = unsigned.split('.');
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `${negative ? '-' : ''}${grouped}${fraction ? `.${fraction}` : ''}`;
}

export function formatMoney(value) {
    return `C$ ${groupedDecimal(money(String(value ?? '0')), 2)}`;
}

export function formatPercent(value) {
    return `${groupedDecimal(value ?? '0', 2)} %`;
}

export function formatValue(value, unit) {
    if (unit === 'monto') return formatMoney(value);
    if (unit === 'porcentaje') return formatPercent(value);

    return groupedDecimal(value ?? '0', 2);
}

export function formatDate(value, options = {}) {
    const date = value ? new Date(`${value}T00:00:00`) : null;
    if (!date || Number.isNaN(date.getTime())) return 'Fecha no disponible';

    return new Intl.DateTimeFormat('es-NI', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        ...options,
    }).format(date);
}

export function formatDateTime(value, timeZone = undefined) {
    const date = value ? new Date(value) : null;
    if (!date || Number.isNaN(date.getTime())) return 'Fecha no disponible';

    try {
        return new Intl.DateTimeFormat('es-NI', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: timeZone || undefined,
        }).format(date);
    } catch {
        return new Intl.DateTimeFormat('es-NI', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(date);
    }
}

export function ageLabel(value) {
    const date = value ? new Date(value) : null;
    const elapsed = date ? Date.now() - date.getTime() : Number.NaN;

    if (!Number.isFinite(elapsed) || elapsed < 0) return 'Fecha no disponible';

    const minutes = Math.floor(elapsed / 60_000);
    if (minutes < 1) return 'Ahora';
    if (minutes < 60) return `Hace ${minutes} min`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `Hace ${hours} h`;

    return `Hace ${Math.floor(hours / 24)} d`;
}
