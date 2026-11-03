<?php

namespace App\Enums;

use App\Concerns\EnumOptions;
use App\Support\Documents\Templates\TemplateDefinition;
use App\Support\Documents\Templates\TemplatePresets;

/**
 * The ready-made templates of quotations, invoices and credit notes.
 *
 * Each one is made with the same rows and elements as the template builder,
 * so a company can open any of them in the builder and make it its own.
 */
enum TemplateLayout: string
{
    use EnumOptions;

    case Classic = 'classic';
    case Modern = 'modern';
    case Minimal = 'minimal';
    case Corporate = 'corporate';
    case Elegant = 'elegant';
    case Fresh = 'fresh';
    case Slate = 'slate';
    case Ledger = 'ledger';
    case Horizon = 'horizon';
    case Noir = 'noir';
    case Breeze = 'breeze';
    case Summit = 'summit';
    case Atlas = 'atlas';
    case Prism = 'prism';
    case Clarity = 'clarity';

    /**
     * Get the display label of the template.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get a short description of the template.
     */
    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Timeless and clear: your details on one side, the document details on the other, framed boxes and a ruled table.',
            self::Modern => 'A coloured band across the top, a tinted strip with the customer and the amount due, and the total in a coloured bar.',
            self::Minimal => 'Plenty of white space, thin lines and no boxes. Quiet and very easy to read.',
            self::Corporate => 'Formal and structured: a coloured title panel, a fully ruled table and boxes for stamp and signature.',
            self::Elegant => 'Centred letterhead, serif type and fine double rules. Refined, for professional services.',
            self::Fresh => 'Light and airy: a thin line along the top, a large title and the amount due right beside the customer.',
            self::Slate => 'A dark header block with your details and the document details, and clean lines below.',
            self::Ledger => 'Accountant style: figures in a typewriter type, a fully ruled table and boxed totals.',
            self::Horizon => 'A bold title with a short accent underline, then three columns: from, bill to and details.',
            self::Noir => 'Black and white with strong type: a big title, a heavy rule and a black table header.',
            self::Breeze => 'Friendly and soft: rounded tinted boxes, a light table header and striped rows.',
            self::Summit => 'A coloured stripe down the left side, your name in colour and the details side by side.',
            self::Atlas => 'A two-tone header: your details on a coloured panel beside a large title.',
            self::Prism => 'A coloured title label, the amount due in colour and a striped table.',
            self::Clarity => 'Centred and calm: logo and title in the middle, from and bill to side by side.',
        };
    }

    /**
     * Get the template made with the builder's rows and elements.
     */
    public function definition(): TemplateDefinition
    {
        return TemplatePresets::for($this);
    }
}
