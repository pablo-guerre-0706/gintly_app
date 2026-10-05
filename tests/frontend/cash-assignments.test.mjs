import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { activeAssignment, AssignmentChangeError, canManageCashAssignments, createdCashAssignmentState, eligibleCashiers, ownCashAssignment, replaceCashAssignment } from '../../resources/js/modules/administration/cash-assignment-rules.js';

const register = { id: 8, branch_id: 2, is_active: true };
const cashier = { id: 4, role: 'ROL-03', profiles: ['cajero', 'facturador'], branch_id: 2, is_active: true };
const assigned = { id: 10, cash_register_id: 8, user_id: 4, branch_id: 2, active: true, cash_register: register };

test('gestión solo para dirección con caja.gestionar; ROL-03 y ROL-SYS quedan excluidos', () => {
    for (const role of ['ROL-01', 'ROL-02']) assert.ok(canManageCashAssignments({ role, capabilities: ['caja.gestionar'] }));
    for (const role of ['ROL-03', 'ROL-SYS']) assert.ok(!canManageCashAssignments({ role, capabilities: ['caja.gestionar'] }));
    assert.ok(!canManageCashAssignments({ role: 'ROL-02', capabilities: [] }));
});

test('candidatos: perfil real, misma sucursal, activo, sin otra asignación ni sesión', () => {
    const users = [cashier, { ...cashier, id: 5, branch_id: 3 }, { ...cashier, id: 6, profiles: ['bodeguero'] },
        { ...cashier, id: 7, is_active: false }, { ...cashier, id: 9, role: 'ROL-02' }, { ...cashier, id: 11, profiles: undefined }];
    assert.deepEqual(eligibleCashiers(users, register, [], []).map(x=>x.id), [4]);
    assert.deepEqual(eligibleCashiers(users, register, [assigned], []), []);
    assert.deepEqual(eligibleCashiers(users, register, [], [{ status: 'abierta', opened_by: 4 }]), []);
    assert.deepEqual(eligibleCashiers(users, { ...register, is_active: false }, [], []), []);
});

test('ROL-03 solo admite una caja propia en su sucursal y rechaza payloads contradictorios', () => {
    const context = { identity: { id: 4 }, branch: { id: 2 } };
    assert.equal(ownCashAssignment([assigned], context), assigned);
    assert.equal(ownCashAssignment([], context), null);
    assert.throws(()=>ownCashAssignment([{ ...assigned, user_id: 99 }], context));
    assert.throws(()=>ownCashAssignment([{ ...assigned, branch_id: 3 }], context));
    assert.throws(()=>ownCashAssignment([assigned, assigned], context));
    assert.throws(()=>activeAssignment([assigned, assigned], 8));
});

test('alta de asignación envía exactamente caja y cajero, sin finalizar nada', async () => {
    let writes=0;
    const result = await replaceCashAssignment({ registerId: 8, userId: 4, assignment: null,
        finish: async()=>assert.fail('No hay asignación para finalizar'),
        assign: async(payload)=>{ writes++; assert.deepEqual(payload, {cash_register_id:8,user_id:4}); return {data:assigned}; } });
    assert.equal(result, assigned); assert.equal(writes, 1);
});

test('cambio conserva historial: DELETE confirmado primero, luego un solo POST', async () => {
    const calls=[];
    await replaceCashAssignment({ assignment: assigned, registerId:8,userId:12,
        finish: async(id)=>{calls.push(['DELETE',id]);return {data:{id:10,active:false,ended_at:'2026-10-04T00:00:00Z'}};},
        assign: async(payload)=>{calls.push(['POST',payload]);return {data:{...assigned,id:11,user_id:12}};} });
    assert.deepEqual(calls,[['DELETE',10],['POST',{cash_register_id:8,user_id:12}]]);
});

test('sesión abierta 409 no finaliza ni ejecuta el segundo paso', async () => {
    const error=Object.assign(new Error('CASH_ASSIGNMENT_OPEN_SESSION'),{status:409});
    await assert.rejects(replaceCashAssignment({assignment:assigned,registerId:8,userId:12,
        finish:async()=>{throw error;},assign:async()=>assert.fail('No se debe asignar')}),error);
});

test('fallo tras finalizar declara estado parcial real, sin rollback ni reintento', async () => {
    let writes=0;
    await assert.rejects(replaceCashAssignment({assignment:assigned,registerId:8,userId:12,
        finish:async()=>({data:{id:10,active:false,ended_at:'2026-10-04T00:00:00Z'}}),
        assign:async()=>{writes++;throw Object.assign(new Error('HTTP 500'),{status:500});}}),AssignmentChangeError);
    assert.equal(writes,1);
});

test('respuesta incierta al finalizar nunca permite crear otra asignación a ciegas', async () => {
    await assert.rejects(replaceCashAssignment({assignment:assigned,registerId:8,userId:12,
        finish:async()=>({data:{id:10,active:true,ended_at:null}}),assign:async()=>assert.fail('Finalización no confirmada')}));
});

test('Personal enlaza a una única gestión y apertura usa una caja de solo lectura', async () => {
    const users = await readFile(new URL('../../resources/js/modules/organization/users/index.js', import.meta.url),'utf8');
    assert.ok(users.includes('root.dataset.cashRegistersUrl')); assert.ok(users.includes('usersWithProfiles'));
    const opening = await readFile(new URL('../../resources/views/operations/cash-open.blade.php', import.meta.url),'utf8');
    assert.ok(opening.includes('name="cash_register_id" readonly')); assert.ok(!opening.includes('<select'));
    const page = await readFile(new URL('../../resources/js/modules/administration/cash-registers.js', import.meta.url),'utf8');
    assert.ok(page.includes('new CashAssignmentDialog')); assert.ok(!page.includes('data.assignment_user_id'));
});

test('diálogo reutiliza el focus trap y libera exclusivamente su bloqueo de scroll', async () => {
    const controller = await readFile(new URL('../../resources/js/modules/administration/cash-assignment-dialog.js', import.meta.url),'utf8');
    assert.ok(controller.includes('createFocusTrap(this.dialog'));
    assert.ok(controller.includes('this.focusTrap.activate('));
    assert.ok(controller.includes('this.focusTrap.deactivate()'));
    assert.ok(controller.includes('unlockScroll(this.owner)'));
});

test('fallo opcional distingue caja creada sin cajero de asignación realmente persistida', () => {
    const empty = createdCashAssignmentState([], 8, 4);
    assert.equal(empty.confirmed, false);
    assert.match(empty.message, /sí fue creada, pero quedó sin cajero/);
    assert.equal(createdCashAssignmentState([assigned], 8, 4).confirmed, true);
    const other = createdCashAssignmentState([{ ...assigned, user_id: 5 }], 8, 4);
    assert.equal(other.confirmed, false);
    assert.match(other.message, /tiene una asignación activa, pero no al cajero solicitado/);
    assert.throws(() => createdCashAssignmentState([assigned, assigned], 8, 4));
});
