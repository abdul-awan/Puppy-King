import { parseDate, toDateInput } from '@/lib/format';
import type { DocumentLine, LineType, TaxRateOption } from '@/types';

/**
 * A line while it is being edited in a form.
 */
export type EditableLine = {
    key: string;
    type: LineType;
    product_id: number | null;
    product_label: string | null;
    description: string;
    details: string;
    show_details: boolean;
    quantity: string | number;
    unit: string;
    unit_price: string | number;
    discount_percent: string | number;
    tax_rate_id: number | null;
    hs_code: string;
};

export type DocumentTotals = {
    lines: number[];
    subtotal: number;
    discountAmount: number;
    netTotal: number;
    taxes: { name: string; rate: number; net: number; tax: number }[];
    taxTotal: number;
    furtherTaxAmount: number;
    total: number;
};

let counter = 0;

export const lineKey = (): string => `line-${Date.now()}-${counter++}`;

/**
 * Round half away from zero to 2 decimals (same as the server).
 */
export const round2 = (value: number): number =>
    (Math.sign(value) * Math.round((Math.abs(value) + Number.EPSILON) * 100)) /
    100;

const toNumber = (value: string | number | null | undefined): number => {
    const number = Number(value ?? 0);

    return Number.isFinite(number) ? number : 0;
};

export function emptyLine(
    type: LineType = 'item',
    taxRateId: number | null = null,
): EditableLine {
    return {
        key: lineKey(),
        type,
        product_id: null,
        product_label: null,
        description: '',
        details: '',
        show_details: false,
        quantity: 1,
        unit: '',
        unit_price: '',
        discount_percent: '',
        tax_rate_id: type === 'item' ? taxRateId : null,
        hs_code: '',
    };
}

export function toEditableLines(lines: DocumentLine[]): EditableLine[] {
    return lines.map((line) => ({
        key: lineKey(),
        type: line.type,
        product_id: line.product_id,
        product_label: null,
        description: line.description,
        details: line.details ?? '',
        show_details: Boolean(line.details),
        quantity: line.type === 'item' ? Number(line.quantity) : 1,
        unit: line.unit ?? '',
        unit_price: line.type === 'item' ? Number(line.unit_price) : '',
        discount_percent: Number(line.discount_percent) || '',
        tax_rate_id: line.tax_rate_id,
        hs_code: line.hs_code ?? '',
    }));
}

/**
 * The payload sent to the server for the lines.
 */
export function linesPayload(lines: EditableLine[]) {
    return lines.map((line) => ({
        type: line.type,
        product_id: line.type === 'item' ? line.product_id : null,
        description: line.description,
        details: line.details || null,
        quantity: line.type === 'item' ? line.quantity : null,
        unit: line.unit || null,
        unit_price: line.type === 'item' ? line.unit_price : null,
        discount_percent:
            line.discount_percent === '' ? 0 : line.discount_percent,
        tax_rate_id: line.type === 'item' ? line.tax_rate_id : null,
        hs_code: line.hs_code || null,
    }));
}

/**
 * Calculate the totals exactly like the server does (for live display).
 */
export function calculateTotals(
    lines: EditableLine[],
    taxRates: TaxRateOption[],
    discountPercent: string | number,
    furtherTaxRate: string | number | null,
): DocumentTotals {
    const documentDiscount = toNumber(discountPercent) / 100;
    const taxes = new Map<
        string,
        { name: string; rate: number; net: number; tax: number }
    >();
    const lineTotals: number[] = [];
    let subtotal = 0;
    let netTotal = 0;
    let taxTotal = 0;
    let taxableNet = 0;

    for (const line of lines) {
        if (line.type !== 'item') {
            lineTotals.push(0);
            continue;
        }

        const taxRate = taxRates.find((rate) => rate.id === line.tax_rate_id);
        const rate = toNumber(taxRate?.rate);
        const lineSubtotal = round2(
            toNumber(line.quantity) *
                toNumber(line.unit_price) *
                (1 - toNumber(line.discount_percent) / 100),
        );
        const lineNet = round2(lineSubtotal * (1 - documentDiscount));
        const lineTax = round2((lineNet * rate) / 100);

        subtotal += lineSubtotal;
        netTotal += lineNet;
        taxTotal += lineTax;

        if (rate > 0) {
            taxableNet += lineNet;
        }

        const key = `${rate}|${taxRate?.name ?? ''}`;
        const entry = taxes.get(key) ?? {
            name: taxRate?.name ?? '',
            rate,
            net: 0,
            tax: 0,
        };
        entry.net += lineNet;
        entry.tax += lineTax;
        taxes.set(key, entry);

        lineTotals.push(lineSubtotal);
    }

    const furtherTaxAmount =
        furtherTaxRate === null || furtherTaxRate === ''
            ? 0
            : round2((taxableNet * toNumber(furtherTaxRate)) / 100);

    return {
        lines: lineTotals,
        subtotal: round2(subtotal),
        discountAmount: round2(subtotal - netTotal),
        netTotal: round2(netTotal),
        taxes: [...taxes.values()].filter((tax) => tax.rate > 0),
        taxTotal: round2(taxTotal),
        furtherTaxAmount,
        total: round2(netTotal + taxTotal + furtherTaxAmount),
    };
}

/**
 * The due date for an invoice date and payment term (same rule as the server).
 */
export function dueDateFor(
    date: string,
    term: { days: number; end_of_month: boolean } | undefined,
): string | null {
    const start = parseDate(date);

    if (!start || !term) {
        return null;
    }

    const due = term.end_of_month
        ? new Date(start.getFullYear(), start.getMonth() + 1, 0)
        : new Date(start);

    due.setDate(due.getDate() + term.days);

    return toDateInput(due);
}
