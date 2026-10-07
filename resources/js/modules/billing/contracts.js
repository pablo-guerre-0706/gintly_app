export const PERIODS = Object.freeze({ monthly: 'Mensual', annual: 'Anual' });
export const STATES = Object.freeze({ none: 'Sin suscripción', pending_payment: 'Pago pendiente', incomplete: 'Confirmación pendiente', active: 'Activa', past_due: 'Renovación pendiente', canceled: 'Renovación cancelada', expired: 'Vencida' });
export const FEATURES = Object.freeze({ pos: 'Punto de venta', sales: 'Ventas', catalog: 'Catálogo', inventory: 'Inventario', cash: 'Caja', returns: 'Devoluciones', receivables: 'Cuentas por cobrar', three_way_match: 'Contraste de compras 3-Way', anomalies: 'Anomalías', supplier_map: 'Mapa de proveedores', multi_branch: 'Múltiples sucursales', advanced_reports: 'Reportes avanzados', warehouse_transfers: 'Traspasos entre bodegas' });
const fail = () => { throw new TypeError('El servidor no devolvió un contrato de suscripción válido. Actualiza el estado; no se ha confirmado ningún pago.'); };
const text = value => typeof value === 'string' && value.trim() !== '';
const date = value => value === null || (text(value) && Number.isFinite(Date.parse(value)));

export function catalog(payload) {
    if (!Array.isArray(payload?.data) || !payload.data.length) fail();
    const keys = new Set();
    for (const plan of payload.data) {
        if (!text(plan.key) || keys.has(plan.key) || !text(plan.name) || plan.currency !== 'NIO') fail();
        keys.add(plan.key);
        for (const period of Object.keys(PERIODS)) {
            const price = plan.prices?.[period];
            if (!Number.isSafeInteger(price?.nio_minor) || price.nio_minor < 1 || !/^\d+\.\d{2}$/.test(price.nio ?? '')
                || BigInt(price.nio.replace('.', '')) !== BigInt(price.nio_minor)) fail();
        }
        if (!Number.isInteger(plan.limits?.branches) || plan.limits.branches < 1
            || !(plan.limits.cash_sessions === null || (Number.isInteger(plan.limits.cash_sessions) && plan.limits.cash_sessions > 0))
            || !Array.isArray(plan.features) || plan.features.some(value => !text(value))) fail();
    }
    return payload.data;
}

export function selection(value, plans) {
    if (!value || Object.keys(value).sort().join(',') !== 'period,plan' || !plans.some(plan => plan.key === value.plan) || !Object.hasOwn(PERIODS, value.period)) throw new TypeError('Selecciona un plan y una periodicidad del catálogo vigente.');
    return Object.freeze({ plan: value.plan, period: value.period });
}

export function subscription(payload) {
    const value = payload?.data;
    if (!value || !Object.hasOwn(STATES, value.status) || typeof value.grants_access !== 'boolean') fail();
    if (!date(value.paid_until) || !(value.plan_key === null || text(value.plan_key))
        || !(value.period === null || Object.hasOwn(PERIODS, value.period))) fail();
    if (value.status === 'none' && (value.grants_access || value.plan_key !== null || value.period !== null || value.paid_until !== null)) fail();
    for (const field of ['renews_at', 'canceled_at']) if (Object.hasOwn(value, field) && !date(value[field])) fail();
    if (value.pending_change !== undefined && value.pending_change !== null) {
        const pending = value.pending_change;
        if (!text(pending.plan_key) || !Object.hasOwn(PERIODS, pending.period) || !date(pending.effective_at)) fail();
    }
    return value;
}

export function checkout(payload, snapshot) {
    const value = payload?.data;
    if (!value || value.plan_key !== snapshot.plan || value.period !== snapshot.period || value.status !== 'created' || !date(value.expires_at)) fail();
    let url;
    try { url = new URL(value.checkout_url); } catch { fail(); }
    if (url.protocol !== 'https:' || !url.hostname || url.username || url.password) fail();
    return value;
}

export function priceText(plan, period) {
    const minor = BigInt(plan.prices[period].nio_minor);
    return `C$ ${new Intl.NumberFormat('es-NI', { maximumFractionDigits: 0 }).format(minor / 100n)}.${String(minor % 100n).padStart(2, '0')}`;
}

export function billingError(error) {
    const messages = {
        SUBSCRIPTION_REQUIRED: 'El negocio no tiene acceso comercial vigente. Consulta el estado o contrata con la cuenta propietaria.',
        PLAN_FEATURE_UNAVAILABLE: 'Esta función no está incluida en el plan vigente. Consulta la suscripción; no se cambió ningún permiso.',
        CHECKOUT_IN_PROGRESS: 'Existe un checkout abierto para otra selección. Complétalo o espera a que venza; no se abrirá otra contratación.',
        CHECKOUT_KEY_EXPIRED: 'Este intento de checkout venció. Puedes iniciar uno nuevo de forma explícita.',
        CHECKOUT_RESULT_UNKNOWN: 'El proveedor todavía no confirma el intento. Conservamos su clave y selección; recupera el mismo intento más tarde.',
        CHECKOUT_IDEMPOTENCY_CONFLICT: 'Esta clave corresponde a una selección distinta. No la cambiaremos automáticamente; solicita revisión del intento.',
        SUBSCRIPTION_ALREADY_ACTIVE: 'El negocio ya tiene una suscripción vigente. Actualizaremos el estado para gestionar esa misma suscripción.',
        NO_ACTIVE_SUBSCRIPTION: 'No hay una suscripción vigente confirmada que pueda gestionarse. Actualiza el estado.',
        PLAN_CHANGE_INVALID: 'El plan y la periodicidad coinciden con los actuales. Elige un destino diferente.',
        PLAN_LIMIT_EXCEEDED: 'El uso actual supera el límite del plan destino. Reduce las sucursales activas o sesiones simultáneas que indica el servidor antes de solicitar el descenso. No borraremos recursos.',
        BILLING_UNAVAILABLE: 'La contratación no está disponible ahora. La configuración o el proveedor requieren atención; no se confirmó ningún pago. Conservamos el intento para su recuperación.',
        LIMIT_CHECK_UNAVAILABLE: 'No se pudo verificar el cupo por una indisponibilidad temporal. Esto no significa que hayas excedido un límite.',
    };
    if (messages[error?.code]) {
        // This public domain message identifies the actual limit/resource; other server failures stay sanitized.
        if (error.code === 'PLAN_LIMIT_EXCEEDED' && text(error.message)) return `${messages[error.code]} ${error.message}`;
        return messages[error.code];
    }
    if (error instanceof TypeError) return error.message;
    if (error?.status === 401) return 'La sesión terminó. Inicia sesión nuevamente.';
    if (error?.status === 403) return 'Esta cuenta no está autorizada o el negocio está suspendido. No lo interpretamos como falta de pago.';
    if (error?.status === 404) return 'El recurso comercial no está disponible. Actualiza el estado.';
    if (error?.status === 409) return 'La solicitud entra en conflicto con el estado comercial. Actualiza el estado antes de continuar.';
    if (error?.status === 419) return 'No se pudo renovar la protección de la sesión. Reintenta de forma explícita.';
    if (error?.status === 422) return 'Revisa el plan y la periodicidad indicados.';
    if (error?.status === 429) return 'Se alcanzó el límite temporal. Espera el plazo indicado antes de volver a consultar o enviar.';
    return 'No pudimos confirmar el resultado. Conservamos los datos; consulta el estado o recupera el mismo intento, sin repetir una contratación nueva.';
}
