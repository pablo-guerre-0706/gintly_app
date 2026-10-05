import { cashRead } from '@/modules/operations/cash-shared';
import { ownCashAssignment } from '@/modules/administration/cash-assignment-rules';

export async function assignmentCollection(path, query = {}, signal = null) {
    const records = [];
    let page = 1; let last = 1;
    do {
        const response = await cashRead(path, { ...query, page, per_page: 100 }, signal);
        if (!Array.isArray(response?.data) || !Number.isInteger(response?.meta?.last_page)) {
            throw new TypeError('El servidor no devolvió una colección paginada válida.');
        }
        records.push(...response.data); last = response.meta.last_page; page += 1;
    } while (page <= last);
    return records;
}

export const getActiveCashAssignments = (query = {}, signal = null) => assignmentCollection('/cash-register-assignments', { ...query, active: true }, signal);

export async function getOwnCashAssignment(context) {
    return ownCashAssignment(await getActiveCashAssignments(), context);
}

export async function usersWithProfiles(users, signal = null) {
    const result = [...users];
    const queue = users.map((user, index) => ({ user, index })).filter(({ user }) => user.role === 'ROL-03');
    // GET /users no incluye profiles. El endpoint dedicado sí carga la relación real.
    await Promise.all(Array.from({ length: Math.min(3, queue.length) }, async () => {
        while (queue.length) {
            const { user, index } = queue.shift();
            const response = await cashRead(`/users/${user.id}/profiles`, {}, signal);
            if (response?.data?.id !== user.id || !Array.isArray(response.data.profiles)) {
                throw new TypeError('No se pudieron confirmar los perfiles del usuario.');
            }
            result[index] = response.data;
        }
    }));
    return result;
}

export async function assignmentDirectory(register, signal = null) {
    const [assignments, sessions, users] = await Promise.all([
        getActiveCashAssignments({}, signal),
        assignmentCollection('/cash-sessions', { status: 'abierta' }, signal),
        assignmentCollection('/users', { branch_id: register.branch_id, is_active: true, sort: 'name', direction: 'asc' }, signal),
    ]);
    return { assignments, sessions, users: await usersWithProfiles(users, signal) };
}
