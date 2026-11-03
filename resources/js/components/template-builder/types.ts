// Types of the template builder (see App\Support\Documents\Templates\TemplateDefinition and ElementTypes).
import type { DocumentPresentation } from '@/types';

export type StyleValues = Record<string, string | number | boolean>;

export type TemplateElement = {
    id: string;
    type: string;
    settings: Record<string, unknown>;
    style: StyleValues;
};

export type TemplateCell = {
    style: StyleValues;
    elements: TemplateElement[];
};

export type TemplateRow = {
    id: string;
    layout: string;
    style: StyleValues;
    cells: TemplateCell[];
};

export type PageSettings = {
    accent: string;
    text: string;
    font: string;
    font_size: string;
    margins: string;
    decoration: string;
    footer_align: string;
    footer_border: string;
    label_color: string;
    label_spacing: number;
};

export type TemplateDefinition = {
    version: number;
    page: PageSettings;
    rows: TemplateRow[];
};

export type ColorTokens = 'text' | 'line' | 'background';

export type FieldDefinition = {
    kind:
        | 'bool'
        | 'choice'
        | 'number'
        | 'color'
        | 'text'
        | 'textarea'
        | 'labels'
        | 'image';
    label: string;
    hint?: string;
    placeholder?: string;
    options?: Record<string, string>;
    min?: number;
    max?: number;
    step?: number;
    length?: number;
    tokens?: ColorTokens;
    default: unknown;
};

export type ElementTypeDefinition = {
    label: string;
    description: string;
    icon: string;
    group: string;
    settings: Record<string, FieldDefinition>;
    style: string[];
    defaults: { settings: Record<string, unknown>; style: StyleValues };
};

export type Registry = {
    types: Record<string, ElementTypeDefinition>;
    groups: Record<string, string>;
    style: Record<string, FieldDefinition>;
    colors: Record<ColorTokens, Record<string, string>>;
};

export type Structure = {
    layouts: Record<string, number[]>;
    row_style: string[];
    cell_style: string[];
    max_rows: number;
    max_elements: number;
};

export type PageChoices = {
    fonts: Record<string, string>;
    font_sizes: Record<string, string>;
    margins: Record<string, string>;
    decorations: Record<string, string>;
    footer_aligns: Record<string, string>;
    footer_borders: Record<string, string>;
    label_colors: Record<string, string>;
};

/** Example data of a kind of document, as printed (see DocumentPdf::sample()). */
export type Sample = {
    type: string;
    title: string;
    view: DocumentPresentation;
    seller: {
        name: string | null;
        address: string[];
        contact: string[];
        tax: string[];
    };
    customer: {
        heading: string;
        name: string;
        address: string | null;
        tax: string[];
    };
    logo: string | null;
    stamp: { text: string; color: string } | null;
    payment: { label: string; lines: string[] } | null;
    sideBox: { label: string; lines: string[] } | null;
    qr: string | null;
    footer: string | null;
    highlight: { label: string; value: string } | null;
    heading: { title: string | null; introduction: string | null };
    closing: string | null;
    companyFooter: string | null;
    signatureLabels: string[];
    placeholders: Record<string, string>;
};

export type TemplatePreset = {
    key: string;
    name: string;
    description: string;
    definition: TemplateDefinition;
};

export type UploadedImage = { name: string; url: string };

export type Selection =
    | { kind: 'page' }
    | { kind: 'row'; rowId: string; cell: number }
    | { kind: 'element'; elementId: string };

export type DragPayload =
    | { kind: 'new-element'; type: string }
    | { kind: 'element'; elementId: string }
    | { kind: 'row'; rowId: string }
    | { kind: 'new-row'; layout: string };

export type DropTarget =
    | { kind: 'cell'; rowId: string; cell: number; index: number }
    | { kind: 'gap'; index: number };
