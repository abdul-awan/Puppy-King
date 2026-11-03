<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    /**
     * List the product categories.
     */
    public function index(): Response
    {
        return Inertia::render('company-settings/ProductCategories', [
            'categories' => ProductCategory::query()
                ->withCount('products')
                ->orderBy('sort')
                ->orderBy('name')
                ->get()
                ->map(fn (ProductCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'sort' => $category->sort,
                    'products_count' => (int) $category->getAttribute('products_count'),
                ]),
        ]);
    }

    /**
     * Store a new product category.
     */
    public function store(Request $request): RedirectResponse
    {
        ProductCategory::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category added.')]);

        return back();
    }

    /**
     * Update a product category.
     */
    public function update(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $productCategory->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return back();
    }

    /**
     * Delete a product category (its products become uncategorised).
     */
    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $productCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return back();
    }

    /**
     * Validate the category input.
     *
     * @return array{name: string, sort: int}
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        return ['name' => $validated['name'], 'sort' => (int) ($validated['sort'] ?? 0)];
    }
}
