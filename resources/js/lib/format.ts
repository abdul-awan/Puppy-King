/**
 * Formatting helpers for dates, money, numbers and durations.
 */

const dateFormatter = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const timeFormatter = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
});

/**
 * Parse an ISO date or date-time string. Date-only values ("2026-09-30")
 * are read as local dates so they never shift by a day.
 */
export function parseDate(value: string | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (dateOnly) {
        return new Date(
            Number(dateOnly[1]),
            Number(dateOnly[2]) - 1,
            Number(dateOnly[3]),
        );
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value: string | null | undefined): string {
    const date = parseDate(value);

    return date ? dateFormatter.format(date) : '—';
}

export function formatDateTime(value: string | null | undefined): string {
    const date = parseDate(value);

    return date ? dateTimeFormatter.format(date) : '—';
}

export function formatTime(value: string | null | undefined): string {
    const date = parseDate(value);

    return date ? timeFormatter.format(date) : '—';
}

/**
 * Convert a Date into a "YYYY-MM-DD" string in local time.
 */
export function toDateInput(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export function today(): string {
    return toDateInput(new Date());
}

/**
 * Format a "YYYY-MM" month as "Sep 2026" (or just "Sep").
 */
export function formatMonth(value: string, short = false): string {
    const [year, month] = value.split('-').map(Number);

    if (!year || !month) {
        return value;
    }

    return new Date(year, month - 1, 1).toLocaleString(
        'en-GB',
        short ? { month: 'short' } : { month: 'short', year: 'numeric' },
    );
}

const moneyFormatters = new Map<string, Intl.NumberFormat>();

export function formatMoney(
    value: string | number | null | undefined,
    currency = 'PKR',
): string {
    const amount = Number(value ?? 0);

    let formatter = moneyFormatters.get(currency);

    if (!formatter) {
        try {
            formatter = new Intl.NumberFormat('en-PK', {
                style: 'currency',
                currency,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        } catch {
            return `${currency} ${amount.toFixed(2)}`;
        }

        moneyFormatters.set(currency, formatter);
    }

    return formatter.format(Number.isFinite(amount) ? amount : 0);
}

export function formatNumber(
    value: string | number | null | undefined,
    maximumFractionDigits = 2,
): string {
    const number = Number(value ?? 0);

    return new Intl.NumberFormat('en-PK', { maximumFractionDigits }).format(
        Number.isFinite(number) ? number : 0,
    );
}

export function formatPercent(
    value: string | number | null | undefined,
): string {
    return `${formatNumber(value, 3)}%`;
}

/**
 * Format a number of minutes as "2h 30m".
 */
export function formatDuration(minutes: number | null | undefined): string {
    const total = Math.max(0, Math.round(minutes ?? 0));
    const hours = Math.floor(total / 60);
    const rest = total % 60;

    if (hours === 0) {
        return `${rest}m`;
    }

    return rest === 0 ? `${hours}h` : `${hours}h ${rest}m`;
}

/**
 * Format a number of minutes as decimal hours, e.g. 90 => "1.50".
 */
export function formatHours(minutes: number | null | undefined): string {
    return ((minutes ?? 0) / 60).toFixed(2);
}

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const relativeFormatter = new Intl.RelativeTimeFormat('en', {
    numeric: 'auto',
});

export function formatRelative(value: string | null | undefined): string {
    const date = parseDate(value);

    if (!date) {
        return '—';
    }

    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeFormatter.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}
