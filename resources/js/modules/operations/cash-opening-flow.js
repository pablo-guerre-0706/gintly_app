export class OpeningVerificationError extends Error {
    constructor(cause) {
        super('No se pudo verificar la sesión activa. Conservamos los importes; verifica el estado antes de permitir otra apertura.', { cause });
        this.name = 'OpeningVerificationError';
    }
}

function uncertain(error) {
    return !Number.isInteger(error?.status) || error.status === 0 || error.status === 409
        || error.status >= 500 || (error.status >= 200 && error.status < 300);
}

export async function openVerifiedSession({ current, post, payload }) {
    // Comprobar de nuevo evita un segundo POST desde una pestaña que quedó obsoleta.
    const existing = await current();
    if (existing) return { kind: 'existing', session: existing };
    let response;
    try {
        response = await post(payload);
        if (!Number.isInteger(response?.data?.id) || response.data.status !== 'abierta') {
            throw new TypeError('La respuesta de apertura no confirmó una sesión válida.');
        }
    } catch (error) {
        if (!uncertain(error)) throw error;
        let session;
        try { session = await current(); } catch (failure) { throw new OpeningVerificationError(failure); }
        if (session) return { kind: 'recovered', session, error };
        throw error; // Sin sesión: conserva datos y permite SOLO un nuevo envío explícito.
    }
    let session;
    try { session = await current(); } catch (failure) { throw new OpeningVerificationError(failure); }
    if (!session || session.id !== response.data.id || session.cash_register_id !== payload.cash_register_id
        || session.opening_amount !== payload.opening_amount || session.opening_amount_usd !== payload.opening_amount_usd) {
        throw new OpeningVerificationError(new TypeError('La sesión activa no coincide con la apertura registrada.'));
    }
    return { kind: 'created', session };
}
