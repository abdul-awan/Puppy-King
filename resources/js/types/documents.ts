// Templates and options of quotations, invoices and credit notes (Company settings → Invoice settings).
import type { TemplateDefinition } from '@/components/template-builder/types';

export type DocumentKind = 'invoice' | 'quotation' | 'credit_note';

/** A template the company can choose: one of the ready-made ones or its own (made with the builder). */
export type DocumentTemplateChoice = {
    key: string;
    id: number | null;
    name: string;
    built_in: boolean;
    layout: string;
    layout_label: string;
    description: string | null;
    definition: TemplateDefinition;
};

/** What documents show (see App\Support\Documents\Templates\DocumentOptions). */
export type DocumentOptions = {
    show_tax: boolean;
    tax_columns: 'rate' | 'amounts' | 'none';
    tax_summary: 'per_rate' | 'single';
    item_numbers: boolean;
    hs_codes: boolean;
    product_codes: boolean;
    units: boolean;
    discount_column: boolean;
    quantity_price: boolean;
    amount_in_words: boolean;
    payment_details: boolean;
    payment_summary: boolean;
    status_stamp: boolean;
    company_tax_numbers: boolean;
    company_contact: boolean;
    customer_tax_numbers: boolean;
    terms: boolean;
    signature: 'none' | 'single' | 'double';
    qr_code: boolean;
    page_numbers: boolean;
    footer_bar: boolean;
    company_footer: boolean;
    closing_note: string | null;
    invoice_title: string | null;
    quotation_title: string | null;
    credit_note_title: string | null;
    customer_heading: string | null;
    date_format: string;
    number_format: 'international' | 'south_asian';
    currency_display: 'code' | 'symbol';
    paper: 'a4' | 'letter' | 'legal';
};

/** A line of a presented document; section headers and text lines only have a description. */
export type PresentedRow = {
    type: 'item' | 'section' | 'text';
    description: string;
    details?: string | null;
    code?: string | null;
    cells?: Record<string, string>;
};

/** A document as the company chose to show it, with formatted values (same as on the PDF). */
export type DocumentPresentation = {
    title: string;
    meta: { label: string; value: string }[];
    customer_heading: string;
    columns: {
        key: string;
        label: string;
        align: 'left' | 'right';
        width: string | null;
    }[];
    rows: PresentedRow[];
    totals: {
        label: string;
        value: string;
        style: 'normal' | 'grand' | 'strong';
    }[];
    tax_note: string | null;
    amount_in_words: string | null;
    terms: string | null;
};
