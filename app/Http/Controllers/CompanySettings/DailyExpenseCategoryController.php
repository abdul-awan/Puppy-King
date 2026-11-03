<?php

namespace App\Http\Controllers\CompanySettings;

use App\Models\DailyExpenseCategory;

class DailyExpenseCategoryController extends SimpleListController
{
    protected function model(): string
    {
        return DailyExpenseCategory::class;
    }

    protected function page(): string
    {
        return 'company-settings/DailyExpenseCategories';
    }

    protected function label(): string
    {
        return 'Daily expense category';
    }
}
