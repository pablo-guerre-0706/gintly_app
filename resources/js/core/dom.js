const HTML_ENTITIES = Object.freeze({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
});

export function escapeHtml(value, fallback = '') {
    return String(value ?? fallback).replace(
        /[&<>"']/g,
        (character) => HTML_ENTITIES[character],
    );
}
