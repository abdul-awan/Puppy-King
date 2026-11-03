<?php

use App\Enums\TeamRole;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxRate;
use Inertia\Testing\AssertableInertia as Assert;

test('products can be created, listed and viewed', function () {
    [$user, $team] = teamOwner();
    $taxRate = TaxRate::withoutGlobalScopes()->where('team_id', $team->id)->where('is_default', true)->firstOrFail();
    $category = withinTeam($team, fn () => ProductCategory::create(['name' => 'Laptops']));

    $this->actingAs($user)
        ->post(route('products.store', ['current_team' => $team->slug]), [
            'type' => 'product',
            'name' => 'Dell Latitude 5540',
            'code' => 'LAP-5540',
            'category_id' => $category->id,
            'unit' => 'pcs',
            'sale_price' => '285000',
            'purchase_price' => '240000',
            'tax_rate_id' => $taxRate->id,
            'hs_code' => '8471.3010',
            'track_stock' => true,
            'stock_quantity' => '12',
            'is_active' => true,
        ])
        ->assertRedirect();

    $product = Product::withoutGlobalScopes()->where('code', 'LAP-5540')->firstOrFail();

    expect($product->team_id)->toBe($team->id)
        ->and($product->stock_quantity)->toBe('12.000')
        ->and($product->hs_code)->toBe('8471.3010');

    $this->get(route('products.index', ['current_team' => $team->slug, 'search' => 'latitude']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.category.name', 'Laptops'));

    $this->get(route('products.show', ['current_team' => $team->slug, 'product' => $product->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/Show')
            ->where('product.margin_percent', 15.8)
            ->where('product.tax_rate.rate', '18.000'));
});

test('product codes are unique within a team', function () {
    [$user, $team] = teamOwner();
    Product::factory()->for($team)->create(['code' => 'DUP-1']);

    $this->actingAs($user)
        ->post(route('products.store', ['current_team' => $team->slug]), [
            'type' => 'service',
            'name' => 'Duplicate',
            'code' => 'DUP-1',
            'unit' => 'hour',
            'sale_price' => '5000',
        ])
        ->assertSessionHasErrors('code');
});

test('stock can be adjusted and is logged', function () {
    [$user, $team] = teamOwner();
    $product = Product::factory()->for($team)->create(['track_stock' => true, 'stock_quantity' => 10]);

    $this->actingAs($user)
        ->post(route('products.stock', ['current_team' => $team->slug, 'product' => $product->id]), [
            'quantity' => '-3.5',
            'reason' => 'Damaged in transit',
        ])
        ->assertRedirect();

    expect($product->fresh()->stock_quantity)->toBe('6.500');

    $entry = ActivityLog::withoutGlobalScopes()->where('subject_id', $product->id)->where('event', 'stock_adjusted')->firstOrFail();

    expect($entry->description)->toBe('Stock -3.5 pcs — Damaged in transit');
});

test('the product lookup returns the data needed for document lines', function () {
    [$user, $team] = teamOwner();
    Product::factory()->for($team)->service()->create(['name' => 'Website maintenance', 'sale_price' => 15000, 'hs_code' => '9983.1100']);
    Product::factory()->for($team)->create(['name' => 'Inactive thing', 'is_active' => false]);

    $this->actingAs($user)
        ->getJson(route('lookup', ['current_team' => $team->slug, 'type' => 'products', 'q' => '']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.label', 'Website maintenance')
        ->assertJsonPath('0.data.unit', 'hour')
        ->assertJsonPath('0.data.sale_price', '15000.0000')
        ->assertJsonPath('0.data.hs_code', '9983.1100');
});

test('members without product access cannot manage products', function () {
    [, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['products' => 'read']);

    $this->actingAs($member)
        ->post(route('products.store', ['current_team' => $team->slug]), [
            'type' => 'product',
            'name' => 'Nope',
            'unit' => 'pcs',
            'sale_price' => '1',
        ])
        ->assertForbidden();
});

test('products can be exported', function () {
    [$user, $team] = teamOwner();
    Product::factory()->for($team)->create(['name' => 'Export Widget']);

    $response = $this->actingAs($user)->get(route('products.export', ['current_team' => $team->slug]));

    $response->assertOk();
    expect($response->streamedContent())->toContain('Export Widget');
});

test('product categories can be managed in the settings', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('settings.product-categories.store', ['current_team' => $team->slug]), ['name' => 'Services'])
        ->assertRedirect();

    $this->get(route('settings.product-categories.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('company-settings/ProductCategories')
            ->where('categories.0.name', 'Services'));
});
