<?php

namespace App\Http\Controllers\CompanySettings;

use App\Models\DealSource;

class DealSourceController extends SimpleListController
{
    protected function model(): string
    {
        return DealSource::class;
    }

    protected function page(): string
    {
        return 'company-settings/DealSources';
    }

    protected function label(): string
    {
        return 'Deal source';
    }
}
