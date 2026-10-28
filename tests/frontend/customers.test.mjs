import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const view = fs.readFileSync(new URL('../../resources/views/customers/index.blade.php', import.meta.url), 'utf8');
const script = fs.readFileSync(new URL('../../resources/js/modules/customers/index.js', import.meta.url), 'utf8');

test('customers layout gives the list and detail explicit responsive spans', () => {
    assert.match(view, /lg:grid-cols-12/);
    assert.match(view, /lg:col-span-7/);
    assert.match(view, /lg:col-span-5/);
    assert.match(view, /data-customer-empty/);
});

test('customers directory exposes search, status and the required actions', () => {
    assert.match(view, /name="search"/);
    assert.match(view, /name="is_active"/);
    assert.match(view, />Todos</);
    assert.match(script, /data-customer-action="view"/);
    assert.match(script, /data-customer-action="edit"/);
    assert.match(script, /Desactivar/);
    assert.match(script, /Reactivar/);
});

test('customers state changes use is_active and never delete records', () => {
    assert.match(script, /api\.put\([^\n]+\{ is_active: activating \}/);
    assert.doesNotMatch(script, /api\.delete\(/);
    assert.match(script, /CUSTOMER_HAS_RECEIVABLES/);
    assert.match(script, /El registro, historial y relaciones se conservarán/);
});

test('customers UI covers loading, empty, error, validation and duplicate submissions', () => {
    assert.match(script, /Cargando clientes/);
    assert.match(script, /No hay clientes para/);
    assert.match(script, /data-customer-detail-retry/);
    assert.match(script, /data-form-error-summary/);
    assert.match(script, /submitFormOnce/);
    assert.match(script, /showFormErrors/);
    assert.match(script, /AbortController/);
});
