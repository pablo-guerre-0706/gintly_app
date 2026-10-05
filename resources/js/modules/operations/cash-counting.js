import { ApiError } from '@/core/api-client';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';
import { formatDateTime } from '@/dashboard/formatters';
import { CashDenominationGrid } from './cash-denomination-grid';
import { cashierContext, cashBusy, cashError, cashFieldErrors, cashLabel, cashNotice, cashPost, cashRead, cashUrls, currentCashSession } from './cash-shared';

function appendLine(list, text) { const row = document.createElement('li'); row.className = 'py-3 text-sm'; row.textContent = text; list.append(row); }

export class CashCounting {
    constructor(root, mode) { this.root = root; this.mode = mode; this.form = root.querySelector('[data-cash-form]'); this.pending = false; this.session = null; this.context = null; this.grid = null; this.scrollOwner = Symbol('cash-close-confirm'); }

    async init() {
        this.form.addEventListener('submit', (event) => { event.preventDefault(); void this.submit(); });
        const dialog = this.root.querySelector('[data-cash-confirm-dialog]');
        dialog?.addEventListener('close', () => unlockScroll(this.scrollOwner));
        dialog?.addEventListener('cancel', (event) => { if (this.pending) event.preventDefault(); });
        this.root.querySelector('[data-cash-history-retry]')?.addEventListener('click', () => { void this.loadHistory(); });
        await this.load();
    }

    async load() {
        this.root.setAttribute('aria-busy', 'true');
        try {
            this.context = await cashierContext(this.mode === 'close' ? 'caja.cerrar' : 'caja.movimiento.crear');
            this.session = await currentCashSession(this.context);
            this.root.querySelector('[data-cash-loading]').hidden = true;
            if (!this.session) { this.root.querySelector('[data-cash-empty]').hidden = false; return; }
            this.root.querySelector('[data-cash-session]').textContent = `Sesión #${this.session.id} · ${this.session.cash_register.name} · Sucursal #${this.context.branch.id} · ${formatDateTime(this.session.opened_at, this.context.business.timezone)}`;
            this.grid = new CashDenominationGrid(this.form);
            (this.root.querySelector('[data-cash-content]') ?? this.form).hidden = false;
            if (this.mode === 'count') await this.loadHistory();
        } catch (error) { this.root.querySelector('[data-cash-loading]').hidden = true; cashNotice(this.root, cashError(error), true); }
        finally { this.root.setAttribute('aria-busy', 'false'); }
    }

    async loadHistory() {
        const list = this.root.querySelector('[data-cash-count-history]');
        const state = this.root.querySelector('[data-cash-history-state]');
        if (!list || !this.session) return;
        state.textContent = 'Cargando arqueos…';
        this.root.querySelector('[data-cash-history-retry]').hidden = true;
        try {
            const response = await cashRead(`/cash-sessions/${this.session.id}/counts`);
            if (!Array.isArray(response?.data)) throw new TypeError('El historial de arqueos no es válido.');
            list.replaceChildren();
            response.data.forEach((count) => appendLine(list, `#${count.id} · ${formatDateTime(count.counted_at, this.context.business.timezone)} · NIO contado ${cashLabel(count.counted_amount)}, esperado ${cashLabel(count.expected_amount)}, diferencia ${cashLabel(count.difference)} · USD contado ${cashLabel(count.counted_amount_usd, 'USD')}, esperado ${cashLabel(count.expected_amount_usd, 'USD')}, diferencia ${cashLabel(count.difference_usd, 'USD')}`));
            state.textContent = response.data.length ? `${response.data.length} arqueo(s) registrado(s). La sesión sigue abierta.` : 'Aún no hay arqueos registrados.';
        } catch (error) { state.textContent = cashError(error); this.root.querySelector('[data-cash-history-retry]').hidden = false; }
    }

    async submit() {
        if (this.pending || !this.session || !this.grid) return;
        cashFieldErrors(this.form);
        const fieldError = this.root.querySelector('[data-cash-field-error]'); fieldError.hidden = true;
        let payload;
        try { payload = this.grid.payload(); }
        catch (error) { fieldError.textContent = error.message; fieldError.hidden = false; return; }
        if (this.mode === 'close') {
            payload.closing_notes = this.form.elements.namedItem('closing_notes').value.trim() || null;
            const dialog = this.root.querySelector('[data-cash-confirm-dialog]');
            dialog.querySelector('[data-cash-confirm-totals]').textContent = `Contado: ${cashLabel(payload.counted_amount)} y ${cashLabel(payload.counted_amount_usd, 'USD')}`;
            lockScroll(this.scrollOwner); dialog.showModal();
            return;
        }
        await this.persist(payload);
    }

    async persist(payload) {
        if (this.pending) return;
        this.pending = true; cashBusy(this.form, true, this.mode === 'close' ? 'Cerrando…' : 'Registrando…');
        const dialog = this.root.querySelector('[data-cash-confirm-dialog]');
        if (dialog?.open) dialog.querySelector('[data-cash-confirm-submit]').disabled = true;
        const path = `/cash-sessions/${this.session.id}/${this.mode === 'close' ? 'close' : 'counts'}`;
        try {
            const response = await cashPost(path, payload, this.mode === 'count' ? 201 : 200);
            this.success(response?.data, false);
        } catch (error) {
            const persisted = this.mode === 'close' && error instanceof ApiError && error.status === 422 && error.code === 'UNRECONCILED_CASH_CLOSING';
            if (persisted) this.success(error.payload?.data?.data ?? error.payload?.data, true);
            else {
                if (error instanceof ApiError && error.status === 422) cashFieldErrors(this.form, error.errors);
                cashNotice(this.root, cashError(error), true);
                dialog?.close();
            }
        } finally { this.pending = false; cashBusy(this.form, false); if (dialog) dialog.querySelector('[data-cash-confirm-submit]').disabled = false; }
    }

    success(result, unreconciled) {
        if (!Number.isInteger(result?.id)) throw new TypeError('No se confirmó el registro. Consulta el historial antes de repetirlo.');
        if (this.mode === 'count') {
            if (result.cash_session_id !== this.session.id || typeof result.expected_amount !== 'string' || typeof result.expected_amount_usd !== 'string') throw new TypeError('El arqueo devuelto no coincide con la sesión.');
            cashNotice(this.root, `Arqueo #${result.id} registrado. NIO: esperado ${cashLabel(result.expected_amount)}, contado ${cashLabel(result.counted_amount)}, diferencia ${cashLabel(result.difference)}. USD: esperado ${cashLabel(result.expected_amount_usd, 'USD')}, contado ${cashLabel(result.counted_amount_usd, 'USD')}, diferencia ${cashLabel(result.difference_usd, 'USD')}. La sesión permanece abierta.`, false);
            void this.loadHistory();
            return;
        }
        if (result.id !== this.session.id || !['cerrada', 'descuadrada'].includes(result.status)) throw new TypeError('El cierre devuelto no coincide con la sesión.');
        this.root.querySelector('[data-cash-confirm-dialog]').close();
        const consolidated = result.consolidated_nio ? ` Consolidado informativo NIO: ${cashLabel(result.consolidated_nio.counted_amount)}; tasa snapshot ${result.consolidated_nio.reference_rate}.` : '';
        cashNotice(this.root, `Sesión #${result.id} ${unreconciled ? 'descuadrada: cierre persistido, no reintentar' : 'cerrada'}. NIO: esperado ${cashLabel(result.expected_amount)}, contado ${cashLabel(result.counted_amount)}, diferencia ${cashLabel(result.difference)}. USD: esperado ${cashLabel(result.expected_amount_usd, 'USD')}, contado ${cashLabel(result.counted_amount_usd, 'USD')}, diferencia ${cashLabel(result.difference_usd, 'USD')}.${consolidated}`, false);
        this.form.hidden = true;
        const link = document.createElement('a'); link.href = `${cashUrls().history}?session=${result.id}`; link.className = 'mt-4 inline-flex min-h-11 items-center rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white'; link.textContent = 'Consultar cierre en historial'; this.root.append(link);
        window.setTimeout(() => window.location.assign(link.href), 5000);
    }
}
