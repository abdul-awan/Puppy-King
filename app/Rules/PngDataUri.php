<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a "data:image/png;base64,…" URI that really contains a PNG image,
 * such as a signature drawn on a canvas.
 */
class PngDataUri implements ValidationRule
{
    private const PREFIX = 'data:image/png;base64,';

    private const SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function __construct(
        private readonly int $maxWidth = 4000,
        private readonly int $maxHeight = 2000,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $binary = is_string($value) && str_starts_with($value, self::PREFIX)
            ? base64_decode(substr($value, strlen(self::PREFIX)), true)
            : false;

        // A PNG starts with its 8 byte signature followed by the IHDR chunk holding the size.
        if ($binary === false || strlen($binary) < 24 || ! str_starts_with($binary, self::SIGNATURE) || substr($binary, 12, 4) !== 'IHDR') {
            $fail('The :attribute must be a PNG image.');

            return;
        }

        /** @var array{width: int, height: int} $size */
        $size = unpack('Nwidth/Nheight', substr($binary, 16, 8));

        if ($size['width'] < 1 || $size['height'] < 1 || $size['width'] > $this->maxWidth || $size['height'] > $this->maxHeight) {
            $fail('The :attribute has an invalid size.');

            return;
        }

        // When GD is installed, make sure the whole image can be decoded (so PDFs can render it).
        if (function_exists('imagecreatefromstring') && @imagecreatefromstring($binary) === false) {
            $fail('The :attribute must be a PNG image.');
        }
    }
}
