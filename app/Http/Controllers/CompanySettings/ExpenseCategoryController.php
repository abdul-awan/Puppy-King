<?php

namespace App\Http\Controllers\CompanySettings;

use App\Models\ExpenseCategory;

class ExpenseCategoryController extends SimpleListController
{
    protected function model(): string
    {
        return ExpenseCategory::class;
    }

    protected function page(): string
    {
        return 'company-settings/ExpenseCategories';
    }

    protected function label(): string
    {
        return 'Expense category';
    }
}
