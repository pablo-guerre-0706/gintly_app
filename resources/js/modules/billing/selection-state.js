import { billingError } from './contracts.js';

// Presentation only: never discard or unlock an unresolved checkout attempt.
export function billingSelectionState({ attempt, busy = false, demo = false, mutationUnknown = false }) {
    const state = attempt?.state;
    const disabled = demo || busy || !attempt || state !== 'editing';
    const recovering = Boolean(attempt && state !== 'editing');
    let message = '', recoveryMessage = '';
    if (demo) message = 'El acceso de demostración no realiza contrataciones ni cambios comerciales.';
    else if (busy) message = 'Procesando la solicitud. Plan y periodicidad permanecen bloqueados hasta recibir la respuesta.';
    else if (!attempt) message = 'Consultando el catálogo y el contexto de contratación. Si falla la carga, pulsa «Actualizar estado».';
    else if (state === 'expired') {
        message = 'Plan y periodicidad están bloqueados: el servidor confirmó que este intento venció. Pulsa «Iniciar nuevo intento tras vencimiento» para elegir otra selección.';
        recoveryMessage = 'El vencimiento fue confirmado por el servidor. Solo tu acción explícita iniciará un intento nuevo.';
    } else if (state === 'blocked') {
        message = 'Plan y periodicidad están bloqueados porque este intento requiere revisión. No se cambiará su clave ni se abrirá otro checkout.';
        recoveryMessage = (attempt.failure ? billingError(attempt.failure) + ' ' : '')
            + 'Actualiza el estado y solicita revisión si el conflicto o la autorización persisten; no descartes la clave para eludir el bloqueo.';
    } else if (state === 'checkout') {
        message = 'Plan y periodicidad están bloqueados: el checkout ya fue preparado. Esto no confirma el pago ni habilita acceso.';
        recoveryMessage = 'Si no se abrió el checkout, vuelve a esta vista para recuperar el mismo intento. No inicies una contratación diferente.';
    } else if (recovering) {
        message = 'Plan y periodicidad están bloqueados porque hay un intento de checkout conservado cuyo resultado debe recuperarse. Pulsa «Recuperar el mismo intento»: se enviarán la misma clave y la misma selección.';
        recoveryMessage = (attempt.failure ? billingError(attempt.failure) + ' ' : '')
            + 'Conservamos la clave y la selección. Recupera el mismo intento; no borres los datos ni abras otra contratación para cambiar el plan.';
    } else if (mutationUnknown) {
        message = 'La modificación anterior tiene un resultado pendiente. Pulsa «Actualizar estado» antes de enviar otro cambio.';
    }
    return { disabled, recovering, message, recoveryMessage };
}
