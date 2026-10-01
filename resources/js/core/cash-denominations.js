const MONEY_PATTERN = /^(\d+)(?:\.(\d{1,2}))?$/;

export function parseMoneyMinor(value) {
    const match = String(value).trim().match(MONEY_PATTERN);
    if (!match) return null;

    return BigInt(match[1]) * 100n
        + BigInt((match[2] ?? '').padEnd(2, '0') || '0');
}

export function formatMoneyMinor(value) {
    const digits = value.toString().padStart(3, '0');
    return `${digits.slice(0, -2)}.${digits.slice(-2)}`;
}

export function denominationTotal(lines) {
    if (!Array.isArray(lines) || lines.length === 0) return null;

    let total = 0n;

    for (const line of lines) {
        const value = parseMoneyMinor(line?.value);
        const quantityText = String(line?.qty ?? '').trim();
        const quantity = /^\d+$/.test(quantityText) ? BigInt(quantityText) : null;

        if (value === null || value <= 0n || quantity === null) return null;

        total += value * quantity;
    }

    return formatMoneyMinor(total);
}
