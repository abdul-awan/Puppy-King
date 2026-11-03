<?php

use App\Enums\TeamRole;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\TaxRate;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a user who owns a (personal) team and return both.
 *
 * @return array{0: User, 1: Team}
 */
function teamOwner(): array
{
    $user = User::factory()->create();

    return [$user, $user->currentTeam];
}

/**
 * Add a new user to the team with the given role and module permissions.
 *
 * @param  array<string, string>  $permissions
 */
function teamMember(Team $team, TeamRole $role = TeamRole::Member, array $permissions = []): User
{
    $user = User::factory()->create();

    $team->memberships()->create([
        'user_id' => $user->id,
        'role' => $role,
        'permissions' => $permissions === [] ? null : $permissions,
    ]);

    $user->switchTeam($team);

    return $user;
}

/**
 * Get the team's default (18%) sales tax rate.
 */
function salesTax(Team $team): TaxRate
{
    return TaxRate::withoutGlobalScopes()->where('team_id', $team->id)->where('is_default', true)->firstOrFail();
}

/**
 * Get one of the team's payment terms by name (e.g. "30 days").
 */
function paymentTerm(Team $team, string $name): PaymentTerm
{
    return PaymentTerm::withoutGlobalScopes()->where('team_id', $team->id)->where('name', $name)->firstOrFail();
}

/**
 * A valid invoice form submission: 10 chairs at 25,000 with 18% sales tax and 5,000 untaxed installation.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function invoicePayload(Team $team, ?Company $company, array $overrides = []): array
{
    return array_merge([
        'company_id' => $company?->id,
        'title' => 'Office furniture',
        'date' => '2026-09-30',
        'payment_term_id' => paymentTerm($team, '30 days')->id,
        'currency' => 'PKR',
        'exchange_rate' => '1',
        'discount_percent' => '0',
        'apply_further_tax' => false,
        'lines' => [
            ['type' => 'item', 'description' => 'Office chair', 'quantity' => '10', 'unit' => 'pcs', 'unit_price' => '25000', 'discount_percent' => '0', 'tax_rate_id' => salesTax($team)->id],
            ['type' => 'item', 'description' => 'Installation', 'quantity' => '1', 'unit' => 'job', 'unit_price' => '5000', 'discount_percent' => '0', 'tax_rate_id' => null],
        ],
    ], $overrides);
}

/**
 * Create a (draft) invoice through the form and return it.
 *
 * @param  TestCase  $test
 * @param  array<string, mixed>  $overrides
 */
function createInvoice($test, User $user, Team $team, ?Company $company, array $overrides = []): Invoice
{
    $test->actingAs($user)
        ->post(route('invoices.store', ['current_team' => $team->slug]), invoicePayload($team, $company, $overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return Invoice::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

/**
 * Create an invoice through the form and issue it.
 *
 * @param  TestCase  $test
 * @param  array<string, mixed>  $overrides
 */
function issuedInvoice($test, User $user, Team $team, ?Company $company, array $overrides = []): Invoice
{
    return createInvoice($test, $user, $team, $company, [...$overrides, 'issue_now' => true]);
}

/**
 * Run a callback with the given team as the current team (so team-scoped
 * models are created for and queried within that team).
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function withinTeam(Team $team, callable $callback): mixed
{
    $current = app(CurrentTeam::class);
    $previous = $current->get();
    $current->set($team);

    try {
        return $callback();
    } finally {
        $current->set($previous);
    }
}
