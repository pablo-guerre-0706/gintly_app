import { denominationTotal, formatMoneyMinor, parseMoneyMinor } from '@/core/cash-denominations';
import { cashLabel } from './cash-shared';

const PRESETS = Object.freeze({
    NIO: Object.freeze(['1000.00', '500.00', '200.00', '100.00', '50.00', '20.00', '10.00', '5.00', '1.00']),
    USD: Object.freeze(['100.00', '50.00', '20.00', '10.00', '5.00', '1.00', '0.25']),
});

function row(currency, value = '', custom = false) {
    const wrapper = document.createElement('div');
    wrapper.className = 'grid grid-cols-[minmax(0,1fr)_72px_minmax(0,1fr)] items-center gap-2 border-t border-slate-100 py-2 text-sm';
    wrapper.dataset.cashDenominationRow = currency;
    const denomination = document.createElement('input');
    denomination.type = 'text'; denomination.inputMode = 'decimal'; denomination.value = value;
    denomination.readOnly = !custom; denomination.setAttribute('aria-label', `Denominación ${currency}`);
    denomination.className = 'min-h-11 min-w-0 rounded-lg border border-slate-300 px-2 text-sm read-only:border-transparent read-only:bg-slate-50';
    const quantity = document.createElement('input');
    quantity.type = 'number'; quantity.min = '0'; quantity.step = '1'; quantity.inputMode = 'numeric'; quantity.value = '0';
    quantity.setAttribute('aria-label', `Unidades de ${value || 'denominación personalizada'} ${currency}`);
    quantity.className = 'min-h-11 min-w-0 rounded-lg border border-slate-300 px-2 text-center text-sm';
    const subtotal = document.createElement('output');
    subtotal.className = 'min-w-0 break-words text-right font-semibold tabular-nums';
    subtotal.textContent = cashLabel('0.00', currency);
    wrapper.append(denomination, quantity, subtotal);
    return wrapper;
}

export class CashDenominationGrid {
    constructor(root) {
        this.root = root;
        this.columns = Object.fromEntries(['NIO', 'USD'].map((currency) => [currency, root.querySelector(`[data-cash-grid="${currency}"]`)]));
        if (!this.columns.NIO || !this.columns.USD) throw new TypeError('Faltan las dos columnas del conteo.');
        for (const currency of ['NIO', 'USD']) {
            const list = this.columns[currency].querySelector('[data-cash-grid-rows]');
            list.replaceChildren(...PRESETS[currency].map((value) => row(currency, value)));
            this.columns[currency].querySelector('[data-cash-add-denomination]').addEventListener('click', () => {
                const custom = row(currency, '', true); list.append(custom); custom.querySelector('input').focus();
            });
            list.addEventListener('input', () => this.update());
        }
        this.update();
    }

    read(currency) {
        const lines = [];
        let firstInvalid = null;
        this.columns[currency].querySelectorAll('[data-cash-denomination-row]').forEach((entry) => {
            const [valueInput, qtyInput] = entry.querySelectorAll('input');
            const value = valueInput.value.trim();
            const qty = qtyInput.value.trim();
            const cents = parseMoneyMinor(value);
            const validQty = /^\d+$/.test(qty) && BigInt(qty) <= BigInt(Number.MAX_SAFE_INTEGER);
            const active = qty !== '' && qty !== '0';
            const idleCustom = !value && (qty === '' || qty === '0');
            const valid = idleCustom || (cents !== null && cents > 0n && validQty);
            if (!valid && (cents === null || cents <= 0n)) valueInput.setAttribute('aria-invalid', 'true');
            else valueInput.removeAttribute('aria-invalid');
            if (!valid && !validQty) qtyInput.setAttribute('aria-invalid', 'true');
            else qtyInput.removeAttribute('aria-invalid');
            if (!valid && !firstInvalid) firstInvalid = cents === null || cents <= 0n ? valueInput : qtyInput;
            const subtotal = idleCustom ? '0.00' : valid && cents !== null && validQty ? formatMoneyMinor(cents * BigInt(qty)) : null;
            entry.querySelector('output').textContent = subtotal === null ? 'Revisar' : cashLabel(subtotal, currency);
            if (active && valid) lines.push({ value, qty: Number(qty) });
        });
        const total = lines.length ? denominationTotal(lines) : '0.00';
        return { lines, total, firstInvalid };
    }

    update() {
        for (const currency of ['NIO', 'USD']) {
            const { total } = this.read(currency);
            this.columns[currency].querySelector('[data-cash-grid-total]').textContent = total === null ? 'Revisar' : cashLabel(total, currency);
        }
    }

    reset() {
        for (const currency of ['NIO', 'USD']) {
            this.columns[currency].querySelector('[data-cash-grid-rows]').replaceChildren(...PRESETS[currency].map((value) => row(currency, value)));
        }
        this.update();
    }

    payload() {
        const nio = this.read('NIO');
        const usd = this.read('USD');
        const invalid = nio.firstInvalid ?? usd.firstInvalid;
        if (invalid) { invalid.focus(); throw new TypeError('Corrige el valor y las unidades de las denominaciones.'); }
        // El Backend exige al menos una línea NIO, aun cuando el conteo físico sea cero.
        const nioLines = nio.lines.length ? nio.lines : [{ value: PRESETS.NIO[0], qty: 0 }];
        return {
            counted_amount: nio.total,
            counted_denominations: nioLines,
            counted_amount_usd: usd.total,
            counted_denominations_usd: usd.lines,
        };
    }
}
