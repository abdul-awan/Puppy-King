<?php

use App\Enums\TeamRole;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

test('the search box finds records in all modules the user may see', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    Contact::factory()->for($team)->create(['first_name' => 'Ali', 'last_name' => 'Indusi']);
    issuedInvoice($this, $user, $team, $company);
    Ticket::factory()->for($team)->create(['subject' => 'Indus printer is broken', 'number' => 'TKT-0001']);
    Company::factory()->create(['name' => 'Indus of another team']);

    $response = $this->actingAs($user)
        ->getJson(route('search', ['current_team' => $team->slug, 'q' => 'indus']))
        ->assertOk();

    $groups = collect($response->json('groups'))->keyBy('label');

    expect($groups->keys()->all())->toEqualCanonicalizing(['Companies', 'Contacts', 'Invoices', 'Tickets'])
        ->and($groups['Companies']['items'])->toHaveCount(1)
        ->and($groups['Companies']['items'][0])->toBe([
            'title' => 'Indus Traders',
            'subtitle' => $company->email ?? ($company->ntn ? "NTN {$company->ntn}" : null),
            'url' => "/{$team->slug}/companies/{$company->id}",
        ])
        ->and($groups['Invoices']['items'][0]['title'])->toBe('INV-2627-0001 · Indus Traders')
        ->and($groups['Tickets']['items'][0]['title'])->toBe('TKT-0001 Indus printer is broken');
});

test('search results skip modules the user may not see', function () {
    [$user, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['invoicing' => 'none', 'crm' => 'none']);
    issuedInvoice($this, $user, $team, Company::factory()->for($team)->create(['name' => 'Indus Traders']));

    $response = $this->actingAs($member)
        ->getJson(route('search', ['current_team' => $team->slug, 'q' => 'Indus']))
        ->assertOk();

    expect($response->json('groups'))->toBe([]);
});

test('the search needs at least two letters', function () {
    [$user, $team] = teamOwner();
    Company::factory()->for($team)->create(['name' => 'A1 Traders']);

    $this->actingAs($user)
        ->getJson(route('search', ['current_team' => $team->slug, 'q' => 'A']))
        ->assertOk()
        ->assertExactJson(['groups' => []]);
});
