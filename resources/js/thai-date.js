const BUDDHIST_YEAR_OFFSET = 543;
const MIN_GREGORIAN_YEAR = 1900;
const MAX_GREGORIAN_YEAR = 2100;

function isoFromParts(year, month, day) {
    if (year < MIN_GREGORIAN_YEAR || year > MAX_GREGORIAN_YEAR) {
        return null;
    }

    const date = new Date(year, month - 1, day);
    if (
        date.getFullYear() !== year
        || date.getMonth() !== month - 1
        || date.getDate() !== day
    ) {
        return null;
    }

    return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function parseToIso(value) {
    const raw = String(value || '').trim();
    if (!raw) {
        return null;
    }

    const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (iso) {
        return isoFromParts(Number(iso[1]), Number(iso[2]), Number(iso[3]));
    }

    const display = raw.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/);
    if (display) {
        let year = Number(display[3]);
        if (year >= 2400) {
            year -= BUDDHIST_YEAR_OFFSET;
        } else if (year < MIN_GREGORIAN_YEAR || year > MAX_GREGORIAN_YEAR) {
            return null;
        }

        return isoFromParts(year, Number(display[2]), Number(display[1]));
    }

    const compact = raw.match(/^(\d{2})(\d{2})(\d{4})$/);
    if (compact) {
        return parseToIso(`${compact[1]}/${compact[2]}/${compact[3]}`);
    }

    return null;
}

export function formatForInput(value) {
    const raw = String(value || '').trim();
    if (!raw) {
        return '';
    }

    const iso = parseToIso(raw);
    if (!iso) {
        return raw;
    }

    const [year, month, day] = iso.split('-').map(Number);
    return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year + BUDDHIST_YEAR_OFFSET}`;
}

export function maskInput(value) {
    const raw = String(value || '').trim();
    const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (iso) {
        return formatForInput(raw);
    }

    const digits = raw.replace(/\D/g, '').slice(0, 8);
    if (digits.length <= 2) {
        return digits.length === 2 ? `${digits}/` : digits;
    }
    if (digits.length <= 4) {
        return `${digits.slice(0, 2)}/${digits.slice(2)}${digits.length === 4 ? '/' : ''}`;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
}

export function backwardDelete(value, selectionStart, selectionEnd = selectionStart) {
    const raw = String(value || '');
    const start = Number(selectionStart);
    const end = Number(selectionEnd);

    if (
        !Number.isInteger(start)
        || !Number.isInteger(end)
        || start !== end
        || start < 2
        || raw[start - 1] !== '/'
    ) {
        return null;
    }

    const slashIsLastCharacter = start === raw.length;
    const nextValue = slashIsLastCharacter
        ? raw.slice(0, start - 2)
        : raw.slice(0, start - 2) + raw.slice(start - 1);

    return {
        value: nextValue,
        selectionStart: start - 2,
        selectionEnd: start - 2,
    };
}

const thaiDate = {
    maskInput,
    parseToIso,
    formatForInput,
    backwardDelete,
};

if (typeof window !== 'undefined') {
    window.tpssThaiDate = thaiDate;
}

export default thaiDate;
