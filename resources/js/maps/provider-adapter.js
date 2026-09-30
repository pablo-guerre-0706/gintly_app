const UNAVAILABLE_MESSAGE = 'No hay un proveedor cartográfico aprobado y configurado para este entorno.';

export function createMapProvider() {
    return Object.freeze({
        configured: false,
        provider: null,
        async initialize() {
            throw new Error(UNAVAILABLE_MESSAGE);
        },
        async searchPlaces() {
            throw new Error(UNAVAILABLE_MESSAGE);
        },
        async requestUserLocation() {
            throw new Error(UNAVAILABLE_MESSAGE);
        },
        normalizeResults() {
            return [];
        },
    });
}
