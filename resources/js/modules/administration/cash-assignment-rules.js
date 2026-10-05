export function canManageCashAssignments(context) {
    return ['ROL-01', 'ROL-02'].includes(context?.role) && context.capabilities?.includes('caja.gestionar');
}

export function isCashier(user) {
    return user?.role === 'ROL-03' && Array.isArray(user.profiles) && user.profiles.includes('cajero');
}

export function eligibleCashiers(users, register, assignments, sessions) {
    if (!register?.is_active) return [];
    return users.filter((user) => isCashier(user) && user.is_active === true && user.branch_id === register.branch_id
        && !assignments.some((assignment) => assignment.active && assignment.user_id === user.id)
        && !sessions.some((session) => session.status === 'abierta' && session.opened_by === user.id));
}

export function activeAssignment(assignments, registerId) {
    const matching = assignments.filter((row) => row.active === true && row.cash_register_id === registerId);
    if (matching.length > 1) throw new TypeError('El servidor devolvió varias asignaciones activas para una caja.');
    return matching[0] ?? null;
}

export function createdCashAssignmentState(assignments, registerId, userId) {
    const assignment = activeAssignment(assignments, registerId);
    if (!assignment) return { confirmed: false, message: 'La caja sí fue creada, pero quedó sin cajero. Puedes asignarlo después desde esta misma gestión.' };
    if (assignment.user_id === userId) return { confirmed: true, message: 'La caja y su asignación están confirmadas por el servidor.' };
    return { confirmed: false, message: 'La caja sí fue creada y tiene una asignación activa, pero no al cajero solicitado. Actualiza la gestión antes de realizar otro cambio.' };
}

export function ownCashAssignment(assignments, context) {
    if (assignments.some((row) => row.active !== true || row.user_id !== context.identity.id || row.branch_id !== context.branch.id
        || row.cash_register?.id !== row.cash_register_id || row.cash_register?.branch_id !== context.branch.id)) {
        throw new TypeError('La asignación recibida no corresponde a tu usuario y sucursal.');
    }
    if (assignments.length > 1) throw new TypeError('El servidor devolvió más de una caja asignada al cajero.');
    return assignments[0] ?? null;
}

export class AssignmentChangeError extends Error {
    constructor(cause) {
        super('La asignación anterior ya quedó finalizada, pero no se confirmó la nueva. Consulta el estado antes de volver a asignar.', { cause });
        this.name = 'AssignmentChangeError';
    }
}

export async function replaceCashAssignment({ assignment, registerId, userId, finish, assign }) {
    if (assignment) {
        const ended = await finish(assignment.id);
        if (ended?.data?.id !== assignment.id || ended.data.active !== false || !ended.data.ended_at) {
            throw new TypeError('No se confirmó la finalización. Verifica el estado antes de repetir la operación.');
        }
    }
    try {
        const response = await assign({ cash_register_id: registerId, user_id: userId });
        if (!Number.isInteger(response?.data?.id) || response.data.active !== true
            || response.data.cash_register_id !== registerId || response.data.user_id !== userId) {
            throw new TypeError('No se confirmó la nueva asignación. Consulta su estado antes de repetirla.');
        }
        return response.data;
    } catch (error) {
        if (assignment) throw new AssignmentChangeError(error);
        throw error;
    }
}
