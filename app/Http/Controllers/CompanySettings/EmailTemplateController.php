<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    /**
     * List the e-mail templates.
     */
    public function index(): Response
    {
        return Inertia::render('company-settings/EmailTemplates', [
            'templates' => EmailTemplate::query()
                ->orderBy('id')
                ->get()
                ->map(fn (EmailTemplate $template) => [
                    'id' => $template->id,
                    'type' => $template->type->value,
                    'type_label' => $template->type->label(),
                    'name' => $template->name,
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'placeholders' => $template->type->placeholders(),
                ]),
        ]);
    }

    /**
     * Update an e-mail template.
     */
    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->update($request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template saved.')]);

        return back();
    }
}
