export function profileCatalogLabels(response) {
    if (!Array.isArray(response?.data) || response.data.length === 0) {
        throw new TypeError('El servidor no devolvió el catálogo de perfiles operativos.');
    }

    const labels = new Map();
    for (const profile of response.data) {
        if (typeof profile.value !== 'string' || !profile.value.trim()
            || typeof profile.label !== 'string' || !profile.label.trim() || labels.has(profile.value)) {
            throw new TypeError('El catálogo de perfiles operativos no es válido.');
        }
        labels.set(profile.value, profile.label);
    }
    return labels;
}

export function assignedProfileLabels(user, catalog) {
    if (user.role !== 'ROL-03') return [];
    if (!Array.isArray(user.profiles)) {
        throw new TypeError('No se pudieron confirmar los perfiles asignados al usuario.');
    }

    return [...new Set(user.profiles)].map((value) => {
        if (typeof value !== 'string' || !catalog.has(value)) {
            throw new TypeError('El perfil asignado no pertenece al catálogo recibido del servidor.');
        }
        return catalog.get(value);
    });
}
