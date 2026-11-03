<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use App\Support\CurrentTeam;
use App\Support\Documents\Templates\TemplateContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Images a company uploads for its templates (a stamp, a seal, a certificate, ...).
 * They are kept with the company's other files and only shown to the company.
 */
class TemplateImageController extends Controller
{
    /**
     * Store an uploaded image (PNG, JPG or GIF; no SVG, which can carry scripts).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,gif', 'max:2048', 'dimensions:max_width=5000,max_height=5000'],
        ]);

        $team = app(CurrentTeam::class)->team();
        $file = $request->file('image');
        $extension = match ($file?->getMimeType()) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            default => null,
        };

        abort_if($file === null || $extension === null, 422, 'Upload a PNG, JPG or GIF image.');

        $name = Str::uuid()->toString().'.'.$extension;
        Storage::disk('local')->putFileAs("teams/{$team->id}/template-images", $file, $name);

        return response()->json([
            'name' => $name,
            'url' => route('settings.template-images.show', ['current_team' => $team->slug, 'name' => $name]),
        ]);
    }

    /**
     * Show an uploaded image of the company.
     */
    public function show(string $name): StreamedResponse
    {
        $team = app(CurrentTeam::class)->team();
        $path = TemplateContext::imagePath($team->id, $name);
        $disk = Storage::disk('local');

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
