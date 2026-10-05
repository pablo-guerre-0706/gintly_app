import test from 'node:test';
import assert from 'node:assert/strict';
import { denominationTotal, parseMoneyMinor } from '../../resources/js/core/cash-denominations.js';

test('arqueo y cierre suman denominaciones en unidades menores exactas', () => {
    assert.equal(denominationTotal([
        { value: '500.00', qty: '2' },
        { value: '200.00', qty: '1' },
        { value: '50.00', qty: '1' },
        { value: '0.50', qty: '1' },
    ]), '1250.50');
    assert.equal(denominationTotal([{ value: '0.10', qty: '3' }]), '0.30');
});

test('admite un arqueo de cero con desglose y rechaza importes inválidos', () => {
    assert.equal(denominationTotal([{ value: '20.00', qty: '0' }]), '0.00');
    assert.equal(denominationTotal([]), null);
    assert.equal(denominationTotal([{ value: '0.001', qty: '1' }]), null);
    assert.equal(denominationTotal([{ value: '-10.00', qty: '1' }]), null);
    assert.equal(denominationTotal([{ value: '10.00', qty: '-1' }]), null);
    assert.equal(parseMoneyMinor('999999999999999.99'), 99999999999999999n);
});

test('NIO y USD se totalizan de forma independiente sin convertir moneda', () => {
    const nio = denominationTotal([{ value: '500.00', qty: 2 }, { value: '0.50', qty: 1 }]);
    const usd = denominationTotal([{ value: '20.00', qty: 3 }, { value: '0.25', qty: 1 }]);
    assert.equal(nio, '1000.50');
    assert.equal(usd, '60.25');
    assert.equal(denominationTotal([{ value: '0.10', qty: 1 }, { value: '0.20', qty: 1 }]), '0.30');
});
