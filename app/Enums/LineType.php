<?php

namespace App\Enums;

/**
 * The kind of row on a quotation, invoice or credit note.
 */
enum LineType: string
{
    /** A priced line (product or free line). */
    case Item = 'item';

    /** A heading that groups the following lines. */
    case Section = 'section';

    /** A line of text without amounts. */
    case Text = 'text';
}
