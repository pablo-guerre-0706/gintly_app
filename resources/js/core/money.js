export const SCALE = Object.freeze({
    MONEY: 2,
    QUANTITY: 3,
    COST: 4,
});

export const ROUNDING = Object.freeze({
    HALF_UP: 'half-up',
    TRUNCATE: 'truncate',
});

export const DEFAULT_ROUNDING = ROUNDING.HALF_UP;

const DECIMAL_PATTERN = /^[+-]?\d+(?:\.\d+)?$/;

const pow10 = (scale) => 10n ** BigInt(scale);

function assertScale(scale) {
    if (!Number.isInteger(scale) || scale < 0) {
        throw new TypeError('La escala debe ser un entero mayor o igual a cero.');
    }
}

function parseDecimal(value) {
    if (typeof value !== 'string') {
        throw new TypeError('Los valores decimales deben enviarse como string.');
    }

    const input = value.trim();

    if (!DECIMAL_PATTERN.test(input)) {
        throw new TypeError(`Decimal inválido: "${value}".`);
    }

    const negative = input.startsWith('-');
    const unsigned = input.replace(/^[+-]/, '');
    const [integer, fraction = ''] = unsigned.split('.');

    const digits = `${integer}${fraction}`.replace(/^0+(?=\d)/, '') || '0';
    let coefficient = BigInt(digits);

    if (negative) {
        coefficient = -coefficient;
    }

    return {
        coefficient,
        scale: fraction.length,
    };
}

function divideRounded(
    numerator,
    denominator,
    mode = DEFAULT_ROUNDING,
) {
    if (denominator === 0n) {
        throw new RangeError('División por cero.');
    }

    const negative = (numerator < 0n) !== (denominator < 0n);
    const absNumerator = numerator < 0n ? -numerator : numerator;
    const absDenominator = denominator < 0n ? -denominator : denominator;

    let quotient = absNumerator / absDenominator;
    const remainder = absNumerator % absDenominator;

    if (
        mode === ROUNDING.HALF_UP &&
        remainder * 2n >= absDenominator
    ) {
        quotient += 1n;
    } else if (
        mode !== ROUNDING.TRUNCATE &&
        mode !== ROUNDING.HALF_UP
    ) {
        throw new TypeError(`Modo de redondeo no soportado: ${mode}.`);
    }

    return negative ? -quotient : quotient;
}

function rescale(
    coefficient,
    currentScale,
    targetScale,
    mode = DEFAULT_ROUNDING,
) {
    assertScale(currentScale);
    assertScale(targetScale);

    if (currentScale === targetScale) {
        return coefficient;
    }

    if (currentScale < targetScale) {
        return coefficient * pow10(targetScale - currentScale);
    }

    return divideRounded(
        coefficient,
        pow10(currentScale - targetScale),
        mode,
    );
}

function fromScaled(coefficient, scale) {
    assertScale(scale);

    const negative = coefficient < 0n;
    const absolute = negative ? -coefficient : coefficient;

    if (scale === 0) {
        return `${negative ? '-' : ''}${absolute}`;
    }

    const digits = absolute
        .toString()
        .padStart(scale + 1, '0');

    const integer = digits.slice(0, -scale);
    const fraction = digits.slice(-scale);

    return `${negative ? '-' : ''}${integer}.${fraction}`;
}

export function decimal(
    value,
    scale,
    mode = DEFAULT_ROUNDING,
) {
    const parsed = parseDecimal(value);

    return fromScaled(
        rescale(
            parsed.coefficient,
            parsed.scale,
            scale,
            mode,
        ),
        scale,
    );
}

export function add(
    left,
    right,
    scale = SCALE.MONEY,
    mode = DEFAULT_ROUNDING,
) {
    const a = parseDecimal(left);
    const b = parseDecimal(right);

    const workingScale = Math.max(a.scale, b.scale);

    const result =
        a.coefficient * pow10(workingScale - a.scale) +
        b.coefficient * pow10(workingScale - b.scale);

    return fromScaled(
        rescale(result, workingScale, scale, mode),
        scale,
    );
}

export function subtract(
    left,
    right,
    scale = SCALE.MONEY,
    mode = DEFAULT_ROUNDING,
) {
    const a = parseDecimal(left);
    const b = parseDecimal(right);

    const workingScale = Math.max(a.scale, b.scale);

    const result =
        a.coefficient * pow10(workingScale - a.scale) -
        b.coefficient * pow10(workingScale - b.scale);

    return fromScaled(
        rescale(result, workingScale, scale, mode),
        scale,
    );
}

export function multiply(
    left,
    right,
    scale = SCALE.MONEY,
    mode = DEFAULT_ROUNDING,
) {
    const a = parseDecimal(left);
    const b = parseDecimal(right);

    const coefficient = a.coefficient * b.coefficient;
    const workingScale = a.scale + b.scale;

    return fromScaled(
        rescale(coefficient, workingScale, scale, mode),
        scale,
    );
}

export function divide(
    left,
    right,
    scale = SCALE.MONEY,
    mode = DEFAULT_ROUNDING,
) {
    const a = parseDecimal(left);
    const b = parseDecimal(right);

    if (b.coefficient === 0n) {
        throw new RangeError('División por cero.');
    }

    let numerator = a.coefficient;
    let denominator = b.coefficient;

    const exponent = b.scale + scale - a.scale;

    if (exponent >= 0) {
        numerator *= pow10(exponent);
    } else {
        denominator *= pow10(-exponent);
    }

    return fromScaled(
        divideRounded(numerator, denominator, mode),
        scale,
    );
}

export function compare(left, right) {
    const a = parseDecimal(left);
    const b = parseDecimal(right);

    const workingScale = Math.max(a.scale, b.scale);

    const leftValue =
        a.coefficient * pow10(workingScale - a.scale);

    const rightValue =
        b.coefficient * pow10(workingScale - b.scale);

    if (leftValue === rightValue) return 0;

    return leftValue > rightValue ? 1 : -1;
}

export function isZero(value) {
    return parseDecimal(value).coefficient === 0n;
}

export const money = (
    value,
    mode = DEFAULT_ROUNDING,
) => decimal(value, SCALE.MONEY, mode);

export const quantity = (
    value,
    mode = DEFAULT_ROUNDING,
) => decimal(value, SCALE.QUANTITY, mode);

export const cost = (
    value,
    mode = DEFAULT_ROUNDING,
) => decimal(value, SCALE.COST, mode);
