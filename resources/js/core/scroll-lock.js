const owners = new Set();

let originalBodyOverflow = '';
let originalDocumentOverflow = '';

function requireOwner(owner) {
    if (!owner) {
        throw new TypeError('Scroll lock requires an owner.');
    }
}

export function lockScroll(owner) {
    requireOwner(owner);

    if (owners.has(owner)) {
        return;
    }

    if (owners.size === 0) {
        originalBodyOverflow = document.body.style.overflow;
        originalDocumentOverflow = document.documentElement.style.overflow;
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
        document.documentElement.dataset.scrollLocked = 'true';
    }

    owners.add(owner);
}

export function unlockScroll(owner) {
    requireOwner(owner);

    if (!owners.delete(owner) || owners.size > 0) {
        return;
    }

    document.body.style.overflow = originalBodyOverflow;
    document.documentElement.style.overflow = originalDocumentOverflow;
    delete document.documentElement.dataset.scrollLocked;
}

export function isScrollLocked(owner = null) {
    return owner ? owners.has(owner) : owners.size > 0;
}
