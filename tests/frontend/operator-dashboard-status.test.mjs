import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const view = fs.readFileSync(new URL('../../resources/views/dashboard/operator.blade.php', import.meta.url), 'utf8');
const script = fs.readFileSync(new URL('../../resources/js/modules/dashboard/operator.js', import.meta.url), 'utf8');

test('operator status block uses a balanced responsive grid without changing shared components', () => {
    assert.match(view, /data-operator-status-grid/);
    assert.match(view, /grid min-w-0 gap-5 lg:grid-cols-2/);
    assert.doesNotMatch(view, /data-operator-status-grid[^>]*xl:grid-cols-4/);
});

test('cashier indicators preserve their API keys and distinguish labels from values', () => {
    assert.match(script, /key: 'sesion_caja_abierta'/);
    assert.match(script, /key: 'cxc_cobrables'/);
    assert.match(script, /label: 'Sesión de caja'/);
    assert.match(script, /label: 'Cuentas por cobrar disponibles para cobro'/);
    assert.match(script, /value === null \? 'Sin sesión abierta' : `Sesión #\$\{value\}`/);
    assert.match(script, /new Intl\.NumberFormat\('es-NI'/);
});

test('operator indicators retain semantic description-list markup and responsive stacking', () => {
    assert.match(script, /document\.createElement\('dl'\)/);
    assert.match(script, /document\.createElement\('dt'\)/);
    assert.match(script, /document\.createElement\('dd'\)/);
    assert.match(script, /sm:grid-cols-\[minmax\(0,1fr\)_minmax\(9rem,auto\)\]/);
    assert.match(script, /data\.sections\[profile\]/);
    assert.match(script, /api\.get\('\/dashboard\/operative'/);
});
