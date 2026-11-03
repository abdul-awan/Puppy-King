<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use App\Support\CurrentTeam;
use App\Support\TeamOptions;
use App\Support\TeamSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingsController extends Controller
{
    /**
     * Show the general company settings.
     */
    public function edit(): Response
    {
        $settings = app(CurrentTeam::class)->team()->teamSettings();

        return Inertia::render('company-settings/General', [
            'settings' => [
                'currency' => $settings->currency(),
                'fiscal_year_start_month' => $settings->fiscalYearStartMonth(),
                'further_tax_enabled' => (bool) $settings->get('further_tax_enabled'),
                'further_tax_rate' => (string) $settings->get('further_tax_rate'),
                'quotation_validity_days' => (int) $settings->get('quotation_validity_days'),
                'reminders' => [
                    'enabled' => (bool) $settings->get('reminders.enabled'),
                    'days_after_due' => array_values((array) $settings->get('reminders.days_after_due', [])),
                ],
                'daily_capacity_minutes' => (int) $settings->get('daily_capacity_minutes'),
                'working_days' => $settings->workingDays(),
                'support_enabled' => (bool) $settings->get('support.enabled'),
            ],
            'supportUrl' => $settings->get('support.enabled') && $settings->get('support.token')
                ? route('public.support.show', ['token' => (string) $settings->get('support.token')])
                : null,
            'currencies' => TeamOptions::currencies(),
        ]);
    }

    /**
     * Update the general company settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', Rule::in(array_keys(TeamOptions::CURRENCIES))],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'further_tax_enabled' => ['boolean'],
            'further_tax_rate' => ['required', 'numeric', 'between:0,100'],
            'quotation_validity_days' => ['required', 'integer', 'between:1,365'],
            'reminders.enabled' => ['boolean'],
            'reminders.days_after_due' => ['array', 'max:5'],
            'reminders.days_after_due.*' => ['integer', 'between:1,365', 'distinct'],
            'daily_capacity_minutes' => ['required', 'integer', 'between:60,1440'],
            'working_days' => ['sometimes', 'array', 'min:1', 'max:7'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
            'support_enabled' => ['sometimes', 'boolean'],
        ]);

        $team = app(CurrentTeam::class)->team();

        // The public support form gets a secret address the first time it is switched on.
        if (array_key_exists('support_enabled', $validated)) {
            $validated['support'] = [
                'enabled' => (bool) $validated['support_enabled'],
                'token' => $team->teamSettings()->get('support.token') ?: Str::random(32),
            ];
            unset($validated['support_enabled']);
        }

        $validated['further_tax_rate'] = number_format((float) $validated['further_tax_rate'], 2, '.', '');

        if (isset($validated['working_days'])) {
            $workingDays = array_map('intval', $validated['working_days']);
            sort($workingDays);
            $validated['working_days'] = $workingDays;
        }
        $days = array_map('intval', $validated['reminders']['days_after_due'] ?? []);
        sort($days);
        $validated['reminders']['days_after_due'] = $days;

        $team->update(['settings' => TeamSettings::merge($team->settings ?? [], $validated)]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }
}
