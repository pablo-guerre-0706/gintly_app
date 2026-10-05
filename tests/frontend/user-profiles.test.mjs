import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { assignedProfileLabels, profileCatalogLabels } from '../../resources/js/modules/organization/users/profile-labels.js';

const catalog = profileCatalogLabels({ data: [
    { value: 'cajero', label: 'Cajero' },
    { value: 'facturador', label: 'Facturador' },
    { value: 'bodeguero', label: 'Bodeguero' },
    { value: 'despachador', label: 'Despachador' },
] });

test('Usuarios muestra cada perfil real, sin depender de caja, ID o posición', () => {
    for (const [value, label] of catalog) {
        assert.deepEqual(assignedProfileLabels({ role: 'ROL-03', id: 900, profiles: [value] }, catalog), [label]);
    }
});

test('perfiles combinados se muestran una sola vez y sin numeración ficticia', () => {
    assert.deepEqual(assignedProfileLabels({ role: 'ROL-03', profiles: ['cajero', 'facturador', 'cajero'] }, catalog), ['Cajero', 'Facturador']);
    assert.deepEqual(assignedProfileLabels({ role: 'ROL-03', profiles: [...catalog.keys()] }, catalog), [...catalog.values()]);
});

test('etiquetas provienen del catálogo real y no de nombres supuestos', () => {
    const renamed = profileCatalogLabels({ data: [{ value: 'cajero', label: 'Etiqueta oficial' }] });
    assert.deepEqual(assignedProfileLabels({ role: 'ROL-03', profiles: ['cajero'] }, renamed), ['Etiqueta oficial']);
});

test('perfiles ausentes o no confirmados no se esconden con No aplica', () => {
    assert.deepEqual(assignedProfileLabels({ role: 'ROL-03', profiles: [] }, catalog), []);
    assert.throws(() => assignedProfileLabels({ role: 'ROL-03' }, catalog));
    assert.throws(() => assignedProfileLabels({ role: 'ROL-03', profiles: ['responsable_compras'] }, catalog));
    for (const role of ['ROL-01', 'ROL-02']) assert.deepEqual(assignedProfileLabels({ role }, catalog), []);
});

test('catálogo vacío, duplicado o sin etiquetas produce error contractual explícito', () => {
    assert.throws(() => profileCatalogLabels({ data: [] }));
    assert.throws(() => profileCatalogLabels({ data: [{ value: 'cajero' }] }));
    assert.throws(() => profileCatalogLabels({ data: [{ value: 'cajero', label: 'Cajero' }, { value: 'cajero', label: 'Otro' }] }));
});

test('directorio usa perfiles reales sin consultar asignaciones de caja ni simular guardado', async () => {
    const controller = await readFile(new URL('../../resources/js/modules/organization/users/index.js', import.meta.url), 'utf8');
    const view = await readFile(new URL('../../resources/views/organization/users/index.blade.php', import.meta.url), 'utf8');
    assert.ok(view.includes('Perfil asignado'));
    assert.ok(view.includes('data-profiles-endpoint="/operative-profiles"'));
    assert.ok(view.includes('Responsable de compras:'));
    assert.ok(view.includes('no asignable todavía'));
    assert.ok(controller.includes('usersWithProfiles(response.data, controller.signal)'));
    assert.ok(!/cashRegistersUrl|getActiveCashAssignments|canManageCashAssignments|No aplica|localStorage|sessionStorage|api\.(post|put|patch)/.test(controller));
    assert.ok(!/Caja asignada|data-cash-registers-url/.test(view));
});
