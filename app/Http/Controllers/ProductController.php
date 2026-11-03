<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CustomFieldResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'category' => $request->integer('category') ?: null,
            'type' => $request->string('type')->toString(),
            'inactive' => $request->boolean('inactive'),
        ];

        $products = Product::query()
            ->with(['category:id,name', 'taxRate:id,name,rate'])
            ->search($filters['search'])
            ->when($filters['category'], fn ($query, $value) => $query->where('category_id', $value))
            ->when($filters['type'], fn ($query, $value) => $query->where('type', $value))
            ->where('is_active', ! $filters['inactive'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('products/Index', [
            'products' => ProductResource::collection($products),
            'filters' => $filters,
            'categories' => $this->categories(),
            'types' => ProductType::options(),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        return Inertia::render('products/Create', $this->formOptions());
    }

    /**
     * Store a newly created product.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create([
            ...$request->validated(),
            'stock_quantity' => $request->validated('stock_quantity') ?? 0,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('products.show', $product);
    }

    /**
     * Display the product.
     */
    public function show(Product $product): Response
    {
        $product->load(['category:id,name', 'taxRate:id,name,rate']);

        return Inertia::render('products/Show', [
            'product' => ProductResource::make($product),
            'timeline' => Timeline::for($product),
            'attachments' => AttachmentResource::collection($product->attachments()->with('user:id,name')->get()),
            'customFields' => CustomFieldResource::collection(Product::customFieldDefinitions()),
        ]);
    }

    /**
     * Show the form for editing the product.
     */
    public function edit(Product $product): Response
    {
        return Inertia::render('products/Edit', [
            'product' => ProductResource::make($product),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the product. Stock is changed through adjustments, not here.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->safe()->except('stock_quantity'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('products.show', $product);
    }

    /**
     * Delete the product. Documents keep their own copy of the line.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('products.index');
    }

    /**
     * Adjust the stock of a product.
     */
    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'not_in:0', 'between:-999999999,999999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $quantity = (float) $validated['quantity'];

        if (! $product->track_stock) {
            $product->update(['track_stock' => true]);
        }

        $product->changeStock((string) $validated['quantity'], $validated['reason'] ?? 'Manual adjustment');

        $product->logActivity(
            'stock_adjusted',
            sprintf('Stock %s%s %s%s', $quantity > 0 ? '+' : '', rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.'), $product->unit, filled($validated['reason'] ?? null) ? ' — '.$validated['reason'] : ''),
            ['quantity' => $quantity, 'reason' => $validated['reason'] ?? null],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock updated.')]);

        return back();
    }

    /**
     * Download the products as a CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Product::query()
            ->with(['category:id,name', 'taxRate:id,name'])
            ->search($request->string('search')->toString())
            ->orderBy('name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Code', 'Type', 'Category', 'Unit', 'Sale price', 'Purchase price', 'Tax rate', 'HS code', 'Stock', 'Active'], ',', '"', '');

            $query->chunk(500, function (Collection $products) use ($out) {
                foreach ($products as $product) {
                    /** @var Product $product */
                    fputcsv($out, [
                        $product->name,
                        (string) $product->code,
                        $product->type->label(),
                        (string) $product->category?->name,
                        $product->unit,
                        $product->sale_price,
                        (string) $product->purchase_price,
                        (string) $product->taxRate?->name,
                        (string) $product->hs_code,
                        $product->track_stock ? $product->stock_quantity : '',
                        $product->is_active ? 'Yes' : 'No',
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, 'products-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Get the option lists for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'types' => ProductType::options(),
            'categories' => $this->categories(),
            'taxRates' => TeamOptions::taxRates(),
            'units' => Product::UNITS,
            'customFields' => CustomFieldResource::collection(Product::customFieldDefinitions()),
        ];
    }

    /**
     * Get the product categories as options.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function categories(): array
    {
        return ProductCategory::query()
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductCategory $category) => ['id' => $category->id, 'name' => $category->name])
            ->all();
    }
}
