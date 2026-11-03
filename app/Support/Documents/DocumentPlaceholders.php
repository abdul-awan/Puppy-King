<?php

namespace App\Support\Documents;

use App\Models\SalesDocument;
use App\Models\User;

/**
 * Placeholder values for the e-mail templates of a document.
 */
final class DocumentPlaceholders
{
    /**
     * Get the placeholder values for a document.
     *
     * @return array<string, string|int|float|null>
     */
    public static function for(SalesDocument $document, ?User $user = null): array
    {
        $team = $document->team()->firstOrFail();
        $department = $document->department ?? $team->defaultDepartment();

        return [
            '{customer_name}' => $document->customerLabel(),
            '{contact_name}' => $document->contact->full_name ?? $document->customerLabel(),
            '{company_name}' => $department?->displayName() ?? $team->name,
            '{user_name}' => $user->name ?? '',
            '{link}' => $document->publicUrl(),
            ...$document->emailPlaceholders(),
        ];
    }
}
