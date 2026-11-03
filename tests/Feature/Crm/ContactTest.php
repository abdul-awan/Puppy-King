<?php

use App\Models\Company;
use App\Models\Contact;
use Inertia\Testing\AssertableInertia as Assert;

test('a contact can be created and linked to a company', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('contacts.store', ['current_team' => $team->slug]), [
            'first_name' => 'Ayesha',
            'last_name' => 'Khan',
            'email' => 'ayesha@example.pk',
            'mobile' => '0300-1234567',
            'cnic' => '3520212345671',
            'companies' => [
                ['company_id' => $company->id, 'position' => 'CFO', 'is_primary' => true, 'is_decision_maker' => true],
            ],
        ])
        ->assertRedirect();

    $contact = Contact::withoutGlobalScopes()->where('email', 'ayesha@example.pk')->firstOrFail();

    expect($contact->team_id)->toBe($team->id)
        ->and($contact->full_name)->toBe('Ayesha Khan')
        ->and($contact->companies()->first()?->id)->toBe($company->id)
        ->and($contact->companies()->first()?->pivot->position)->toBe('CFO');
});

test('a contact cannot be linked to a company of another team', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();
    $foreign = Company::factory()->for($otherTeam)->create();

    $this->actingAs($user)
        ->post(route('contacts.store', ['current_team' => $team->slug]), [
            'last_name' => 'Ali',
            'companies' => [['company_id' => $foreign->id]],
        ])
        ->assertSessionHasErrors('companies.0.company_id');
});

test('contacts can be found by first and last name together', function () {
    [$user, $team] = teamOwner();
    Contact::factory()->for($team)->create(['first_name' => 'Bilal', 'last_name' => 'Ahmed']);
    Contact::factory()->for($team)->create(['first_name' => 'Sana', 'last_name' => 'Ahmed']);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team->slug, 'search' => 'bilal ahmed']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('crm/contacts/Index')
            ->has('contacts.data', 1)
            ->where('contacts.data.0.full_name', 'Bilal Ahmed'));
});

test('a contact page shows the linked companies', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Habib Traders']);
    $contact = Contact::factory()->for($team)->create();
    $contact->companies()->attach($company->id, ['position' => 'Owner']);

    $this->actingAs($user)
        ->get(route('contacts.show', ['current_team' => $team->slug, 'contact' => $contact->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('crm/contacts/Show')
            ->where('contact.companies.0.name', 'Habib Traders')
            ->where('contact.companies.0.position', 'Owner'));
});

test('an existing contact can be linked to and unlinked from a company', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create();
    $params = ['current_team' => $team->slug, 'company' => $company->id];

    $this->actingAs($user)
        ->post(route('companies.contacts.store', $params), ['contact_id' => $contact->id, 'position' => 'Buyer'])
        ->assertRedirect();

    expect($company->contacts()->count())->toBe(1);

    $this->delete(route('companies.contacts.destroy', [...$params, 'contact' => $contact->id]))
        ->assertRedirect();

    expect($company->contacts()->count())->toBe(0);
});
