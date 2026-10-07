import { notify } from './notifications';
let initialized = false, navigating = false, featureShown = false;
export function initCommercialRecovery() {
    if (initialized) return;
    initialized = true;
    window.addEventListener('gintly:commercial-error', event => {
        const code = event.detail?.code;
        if (code === 'SUBSCRIPTION_REQUIRED') {
            const target = document.querySelector('meta[name="billing-url"]')?.content;
            if (target && !navigating && !window.location.pathname.startsWith('/billing')) { navigating = true; window.location.assign(target); }
        } else if (code === 'PLAN_FEATURE_UNAVAILABLE' && !featureShown && !window.location.pathname.startsWith('/billing')) {
            featureShown = true;
            notify({ type: 'error', title: 'Función no incluida', message: 'El plan vigente no incluye esta función. Consulta la suscripción desde el menú de cuenta.', persistent: true });
        }
    });
}
