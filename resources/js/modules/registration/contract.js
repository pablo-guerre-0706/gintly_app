export const FIELDS = Object.freeze({
    'owner.first_name': { step: 1, label: 'Nombres' },
    'owner.last_name': { step: 1, label: 'Apellidos' },
    'owner.email': { step: 1, label: 'Correo electrónico' },
    'owner.password': { step: 1, label: 'Contraseña' },
    'owner.password_confirmation': { step: 1, label: 'Confirmación de contraseña' },
    'business.name': { step: 2, label: 'Nombre del negocio' },
    'business.timezone': { step: 2, label: 'Zona horaria' },
});

const length = (value) => [...value].length;
const squish = (value) => value.replace(/\s+/gu, ' ').trim();

export function registrationPayload(values) {
    return {
        business: { name: String(values['business.name'] ?? '').trim(), timezone: String(values['business.timezone'] ?? '') },
        owner: {
            first_name: squish(String(values['owner.first_name'] ?? '')),
            last_name: squish(String(values['owner.last_name'] ?? '')),
            email: String(values['owner.email'] ?? '').trim().toLowerCase(),
            // Nunca normalizar, recortar o truncar estos dos valores.
            password: String(values['owner.password'] ?? ''),
            password_confirmation: String(values['owner.password_confirmation'] ?? ''),
        },
    };
}

export function validateRegistration(payload, timezones) {
    const errors = {};
    const add = (path, message) => { errors[path] = [message]; };
    const { owner, business } = payload;
    for (const field of ['first_name', 'last_name']) {
        if (!owner[field]) add(`owner.${field}`, 'Este campo es obligatorio.');
        else if (length(owner[field]) > 150) add(`owner.${field}`, 'No puede exceder 150 caracteres.');
    }
    if (length(`${owner.first_name} ${owner.last_name}`.trim()) > 150) add('owner.first_name', 'Nombres y apellidos combinados no pueden exceder 150 caracteres.');
    if (!owner.email || length(owner.email) > 180 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/u.test(owner.email)) add('owner.email', 'Introduce un correo válido de hasta 180 caracteres.');
    // Password::defaults(): min(12), letters(), numbers(), symbols(). La comprobación
    // uncompromised() es exclusivamente Backend; no consultamos correos ni contraseñas.
    if (length(owner.password) < 12 || !/\p{L}/u.test(owner.password) || !/\p{N}/u.test(owner.password) || !/[\p{Z}\p{S}\p{P}]/u.test(owner.password)) add('owner.password', 'Utiliza al menos 12 caracteres con letras, números y símbolos.');
    if (!owner.password_confirmation || owner.password !== owner.password_confirmation) add('owner.password_confirmation', 'Las contraseñas deben coincidir exactamente, incluidos los espacios.');
    if (!business.name || length(business.name) > 150) add('business.name', 'Introduce un nombre de negocio de hasta 150 caracteres.');
    if (length(business.timezone) > 64 || !timezones.includes(business.timezone)) add('business.timezone', 'Selecciona una zona horaria válida.');
    return errors;
}

export function registrationResult(response) {
    const result = response?.data;
    if (!result || Object.keys(response).length !== 1 || Object.keys(result).length !== 2
        || typeof result.business_slug !== 'string' || !result.business_slug.trim() || result.business_slug.length > 160
        || typeof result.owner_email !== 'string' || !result.owner_email.trim() || result.owner_email.length > 180) {
        throw new Error('registration_response_invalid');
    }
    return Object.freeze({ business_slug: result.business_slug, owner_email: result.owner_email });
}

export function firstErrorField(errors) {
    return Object.keys(FIELDS).find((path) => Array.isArray(errors?.[path]) && errors[path].length) ?? null;
}

export function retryDelay(value, now = Date.now()) {
    if (typeof value !== 'string' || !value.trim()) return 60000;
    if (/^\d+$/.test(value.trim())) {
        const duration = Number(value.trim()) * 1000;
        return Number.isFinite(duration) ? duration : 60000;
    }
    const date = Date.parse(value);
    return Number.isFinite(date) ? Math.max(0, date - now) : 60000;
}
