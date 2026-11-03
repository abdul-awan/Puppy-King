<?php

namespace App\Http\Controllers\CompanySettings;

use App\Models\LostReason;

class LostReasonController extends SimpleListController
{
    protected function model(): string
    {
        return LostReason::class;
    }

    protected function page(): string
    {
        return 'company-settings/LostReasons';
    }

    protected function label(): string
    {
        return 'Lost reason';
    }
}
