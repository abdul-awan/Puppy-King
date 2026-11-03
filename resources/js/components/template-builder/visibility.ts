// Whether an element has something to show on the example document (TemplateContext::visible()).
import type { Sample, TemplateElement } from './types';

// Settings are normalised by the server; a text setting is a string or missing.
const asText = (value: unknown): string =>
    typeof value === 'string' ? value : '';

export const signatureLabels = (
    element: TemplateElement,
    sample: Sample,
): string[] =>
    element.settings.mode === 'custom'
        ? ((element.settings.labels as string[] | undefined) ?? [])
        : sample.signatureLabels;

export const hasContent = (
    element: TemplateElement,
    sample: Sample,
    images: Record<string, string>,
): boolean => {
    const settings = element.settings;

    switch (element.type) {
        case 'logo':
            return sample.logo !== null;
        case 'company':
            return (
                Boolean(sample.seller.name) || sample.seller.address.length > 0
            );
        case 'stamp':
            return sample.stamp !== null;
        case 'details':
            return sample.view.meta.length > 0;
        case 'extra':
            return sample.sideBox !== null;
        case 'heading':
            return (
                (Boolean(settings.show_title) &&
                    Boolean(sample.heading.title)) ||
                (Boolean(settings.show_introduction) &&
                    Boolean(sample.heading.introduction))
            );
        case 'amount_due':
            return sample.highlight !== null;
        case 'words':
            return sample.view.amount_in_words !== null;
        case 'payment':
            return sample.payment !== null;
        case 'qr':
            return sample.qr !== null;
        case 'terms':
            return sample.view.terms !== null;
        case 'signature':
            return signatureLabels(element, sample).length > 0;
        case 'acceptance':
            return false;
        case 'closing':
            return sample.closing !== null;
        case 'footer_text':
            return Boolean(sample.companyFooter);
        case 'text':
            return asText(settings.text).trim() !== '';
        case 'image':
            return Boolean(images[asText(settings.image)]);
        default:
            return true;
    }
};
