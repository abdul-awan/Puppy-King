/**
 * Read a duration typed by a person: "1:30", "1.5", "1,5", "90m", "2h", "2h 15m" or plain minutes ("45").
 * Returns the number of minutes, or null when it cannot be read.
 */
export function parseDuration(
    value: string | number | null | undefined,
): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    const text = String(value).trim().toLowerCase().replace(',', '.');

    if (text === '') {
        return null;
    }

    const clock = /^(\d+):([0-5]?\d)$/.exec(text);

    if (clock) {
        return Number(clock[1]) * 60 + Number(clock[2]);
    }

    const units = /^(?:(\d+(?:\.\d+)?)\s*h)?\s*(?:(\d+)\s*m(?:in)?)?$/.exec(
        text,
    );

    if (units && (units[1] || units[2])) {
        return Math.round(Number(units[1] ?? 0) * 60) + Number(units[2] ?? 0);
    }

    if (/^\d+(\.\d+)?$/.test(text)) {
        const number = Number(text);

        // "1.5" means hours; a whole number above 12 is taken as minutes ("45").
        return Number.isInteger(number) && number > 12
            ? number
            : Math.round(number * 60);
    }

    return null;
}

/**
 * Show minutes the way they are typed back: "1:30".
 */
export function durationInput(minutes: number | null | undefined): string {
    if (!minutes) {
        return '';
    }

    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`;
}
