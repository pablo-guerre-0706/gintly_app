import { ApiError } from '@/core/api-client';
import { cancelActiveAnomaliesRequest, getActiveAnomalies } from '@/data/anomalies';

const REFRESH_ON_FOCUS_AFTER = 120_000;

function ageLabel(value) {
    const detected = new Date(value);
    const elapsed = Date.now() - detected.getTime();

    if (!Number.isFinite(elapsed) || elapsed < 0) return 'Fecha no disponible';

    const minutes = Math.floor(elapsed / 60_000);
    if (minutes < 1) return 'Ahora';
    if (minutes < 60) return `Hace ${minutes} min`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `Hace ${hours} h`;

    const days = Math.floor(hours / 24);
    return `Hace ${days} d`;
}

function errorMessage(error) {
    if (!(error instanceof ApiError)) return 'No fue posible consultar las anomalías.';
    if (error.status === 403) return 'Tu cuenta no puede consultar anomalías.';
    if (error.status === 419) return 'La sesión de seguridad expiró.';
    if (error.status === 429) return 'Se alcanzó el límite temporal de consultas.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo consultar las anomalías.';

    return error.message || 'No fue posible consultar las anomalías.';
}

function severityClasses(severity) {
    return {
        critica: 'bg-red-100 text-red-700',
        advertencia: 'bg-amber-100 text-amber-800',
        informativa: 'bg-sky-100 text-sky-800',
    }[severity] ?? 'bg-slate-100 text-slate-700';
}

export class AnomalyBellController {
    constructor({ onBeforeOpen = null } = {}) {
        this.root = document.querySelector('[data-anomaly-bell]');
        this.trigger = document.querySelector('[data-anomaly-trigger]');
        this.panel = document.querySelector('[data-anomaly-panel]');
        this.badge = document.querySelector('[data-anomaly-count]');
        this.summary = document.querySelector('[data-anomaly-summary]');
        this.list = document.querySelector('[data-anomaly-list]');
        this.loading = document.querySelector('[data-anomaly-loading]');
        this.empty = document.querySelector('[data-anomaly-empty]');
        this.error = document.querySelector('[data-anomaly-error]');
        this.errorMessage = document.querySelector('[data-anomaly-error-message]');
        this.retry = document.querySelector('[data-anomaly-retry]');
        this.refresh = document.querySelector('[data-anomaly-refresh]');
        this.live = document.querySelector('[data-anomaly-live]');

        this.onBeforeOpen = onBeforeOpen;
        this.enabled = false;
        this.opener = null;
        this.lastFetchedAt = 0;
        this.request = null;
        this.initialized = false;
    }

    init() {
        if (!this.root || this.initialized) return;

        this.initialized = true;
        this.trigger?.addEventListener('click', () => this.toggle());
        this.retry?.addEventListener('click', () => this.load({ force: true }));
        this.refresh?.addEventListener('click', () => this.load({ force: true }));

        document.addEventListener('pointerdown', (event) => {
            if (this.isOpen() && !this.root.contains(event.target)) {
                this.close();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.isOpen()) {
                event.preventDefault();
                this.close({ restoreFocus: true });
            }
        });
        window.addEventListener('focus', () => {
            if (
                this.enabled
                && this.lastFetchedAt > 0
                && Date.now() - this.lastFetchedAt >= REFRESH_ON_FOCUS_AFTER
            ) {
                void this.load({ force: true });
            }
        });
        window.addEventListener('pagehide', cancelActiveAnomaliesRequest, { once: true });
        document.addEventListener('gintly:anomalies-invalidated', () => {
            if (!this.enabled) return;
            if (this.request) {
                void this.request.finally(() => this.load({ force: true }));
                return;
            }
            void this.load({ force: true });
        });
    }

    setContext(context) {
        const canView = context.capabilities.includes('anomalias.ver');

        this.enabled = canView;
        this.root.hidden = !canView;
        this.trigger.disabled = !canView;

        if (!canView) {
            this.close();
            return;
        }

        void this.load();
    }

    setUnavailable() {
        this.enabled = false;
        this.root.hidden = true;
        this.close();
    }

    toggle() {
        if (!this.enabled) return;
        this.isOpen() ? this.close({ restoreFocus: true }) : this.open();
    }

    open() {
        if (!this.enabled || this.isOpen()) return;

        this.onBeforeOpen?.();
        this.opener = document.activeElement;
        this.panel.hidden = false;
        this.trigger.setAttribute('aria-expanded', 'true');
        this.refresh?.focus();
    }

    close({ restoreFocus = false } = {}) {
        if (!this.isOpen()) return;

        this.panel.hidden = true;
        this.trigger.setAttribute('aria-expanded', 'false');

        if (restoreFocus && this.opener instanceof HTMLElement) {
            this.opener.focus();
        }

        this.opener = null;
    }

    isOpen() {
        return this.panel?.hidden === false;
    }

    async load({ force = false } = {}) {
        if (!this.enabled) return null;
        if (this.request) return this.request;

        this.setState('loading');
        this.request = getActiveAnomalies({ force })
            .then((result) => {
                this.lastFetchedAt = Date.now();
                this.render(result);
                return result;
            })
            .catch((error) => {
                if (error instanceof ApiError && error.code === 'request_aborted') return null;
                this.renderError(error);
                return null;
            })
            .finally(() => {
                this.request = null;
            });

        return this.request;
    }

    setState(state) {
        this.loading.hidden = state !== 'loading';
        this.empty.hidden = state !== 'empty';
        this.error.hidden = state !== 'error';
        this.list.hidden = state !== 'ready';
        this.refresh.disabled = state === 'loading';
        this.retry.disabled = state === 'loading';
        this.trigger.setAttribute('aria-busy', String(state === 'loading'));
    }

    render(result) {
        const previousCount = this.badge.hidden ? null : this.badge.textContent;
        const displayCount = result.total > 99 ? '99+' : String(result.total);

        this.badge.textContent = displayCount;
        this.badge.hidden = result.total === 0;
        this.trigger.setAttribute(
            'aria-label',
            result.total === 0
                ? 'No hay anomalías activas'
                : `${result.total} anomalías activas`,
        );
        this.summary.textContent = [
            `${result.totals.detectada ?? 0} detectadas`,
            `${result.totals.notificada ?? 0} notificadas`,
            `${result.totals.en_revision ?? 0} en revisión`,
        ].join(' · ');

        this.list.replaceChildren();
        result.records.forEach((record) => {
            const row = document.createElement('li');
            const top = document.createElement('div');
            const title = document.createElement('p');
            const severity = document.createElement('span');
            const meta = document.createElement('p');
            const details = document.createElement('p');

            row.className = 'rounded-xl border border-slate-200 px-3 py-3';
            top.className = 'flex items-start gap-2';
            title.className = 'min-w-0 flex-1 text-sm font-semibold text-gintly-text-primary';
            title.textContent = record.rule?.name
                ?? record.rule?.code_label
                ?? `Anomalía #${record.id}`;
            severity.className = `shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold ${severityClasses(record.severity)}`;
            severity.textContent = record.severity_label ?? record.severity ?? 'Sin severidad';
            meta.className = 'mt-1 text-xs text-gintly-text-secondary';
            meta.textContent = [
                record.status_label ?? record.status,
                ageLabel(record.detected_at),
            ].filter(Boolean).join(' · ');
            details.className = 'mt-1 text-xs font-medium text-gintly-brand';
            details.textContent = record.difference !== null && record.difference !== undefined
                ? `Diferencia: ${record.difference}`
                : (record.rule?.code ?? record.source_type ?? '');
            details.hidden = details.textContent === '';

            top.append(title, severity);
            row.append(top, meta, details);
            this.list.appendChild(row);
        });

        this.setState(result.records.length === 0 ? 'empty' : 'ready');

        if (previousCount !== null && previousCount !== displayCount) {
            this.live.textContent = `El total de anomalías activas cambió a ${result.total}.`;
        }
    }

    renderError(error) {
        const message = errorMessage(error);
        this.errorMessage.textContent = message;
        this.live.textContent = message;
        this.setState('error');
    }
}
