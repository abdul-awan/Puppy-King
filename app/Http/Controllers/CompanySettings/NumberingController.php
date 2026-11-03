<?php

namespace App\Http\Controllers\CompanySettings;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Support\CurrentTeam;
use App\Support\DocumentNumberer;
use App\Support\TeamSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NumberingController extends Controller
{
    /**
     * Show the numbering formats with a preview of the next number.
     */
    public function edit(DocumentNumberer $numberer): Response
    {
        $team = app(CurrentTeam::class)->team();
        $settings = $team->teamSettings();
        $department = $team->defaultDepartment();

        return Inertia::render('company-settings/Numbering', [
            'types' => collect(DocumentType::cases())->map(fn (DocumentType $type) => [
                'type' => $type->value,
                'label' => $type->label(),
                ...$settings->numbering($type),
                'next' => $numberer->preview($team, $type, now(), $department),
                'per_department' => $type->numberedPerDepartment(),
            ])->values(),
        ]);
    }

    /**
     * Save the numbering formats and optionally reset the next number.
     */
    public function update(Request $request, DocumentNumberer $numberer): RedirectResponse
    {
        $rules = [];

        foreach (DocumentType::cases() as $type) {
            $rules["numbering.{$type->value}.pattern"] = ['required', 'string', 'max:40', 'regex:/\{SEQ\}/', 'regex:/^[A-Za-z0-9\/\-_.{} ]+$/'];
            $rules["numbering.{$type->value}.padding"] = ['required', 'integer', 'between:1,10'];
            $rules["numbering.{$type->value}.next_number"] = ['nullable', 'integer', 'min:1', 'max:99999999'];
        }

        $validated = $request->validate($rules, [
            'numbering.*.pattern.regex' => 'The pattern must contain {SEQ} and may only use letters, digits, / - _ . and the tokens.',
        ]);

        $team = app(CurrentTeam::class)->team();
        $numbering = [];

        foreach (DocumentType::cases() as $type) {
            $numbering[$type->value] = [
                'pattern' => $validated['numbering'][$type->value]['pattern'],
                'padding' => (int) $validated['numbering'][$type->value]['padding'],
            ];
        }

        $team->update(['settings' => TeamSettings::merge($team->settings ?? [], ['numbering' => $numbering])]);

        foreach (DocumentType::cases() as $type) {
            $next = $validated['numbering'][$type->value]['next_number'] ?? null;

            if ($next !== null) {
                $numberer->setNext($team->fresh() ?? $team, $type, now(), (int) $next, $team->defaultDepartment());
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Numbering saved.')]);

        return back();
    }
}
