<?php

namespace App\Support\Documents\Templates;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Makes QR codes (as SVG images) for documents, e.g. the link to the online invoice.
 */
final class QrCode
{
    /**
     * Get a QR code of the text as an SVG data URI.
     */
    public static function dataUri(string $text, int $size = 160): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
