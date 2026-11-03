<?php

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * A platform administrator (with a company of their own, like every account).
 */
function platformAdmin(): User
{
    [$user] = teamOwner();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

test('only platform administrators can open the admin area', function () {
    [$owner, $team] = teamOwner();

    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

    $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($owner)->get(route('admin.companies.index'))->assertForbidden();
    $this->actingAs($owner)->post(route('admin.companies.suspend', $team))->assertForbidden();
    $this->actingAs($owner)->post(route('admin.users.grant', $owner))->assertForbidden();

    expect($team->fresh()->isSuspended())->toBeFalse()
        ->and($owner->fresh()->isPlatformAdmin())->toBeFalse();

    $this->actingAs(platformAdmin())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('stats.companies', 2)
            ->where('stats.users', 2)
            ->where('stats.admins', 1)
            ->has('signups', 12)
            ->where('auth.isPlatformAdmin', true));
});

test('administrators see every company and user of the platform', function () {
    $admin = platformAdmin();
    [$owner, $team] = teamOwner();
    $owner->update(['email' => 'owner@indus.test']);
    $team->update(['name' => 'Indus Traders']);
    Company::factory()->for($team)->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.companies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/companies/Index')
            ->has('companies.data', 2));

    $this->get(route('admin.companies.index', ['search' => 'owner@indus']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.name', 'Indus Traders')
            ->where('companies.data.0.owner.email', 'owner@indus.test'));

    $this->get(route('admin.companies.show', $team->fresh()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/companies/Show')
            ->where('company.name', 'Indus Traders')
            ->has('members', 1)
            ->where('members.0.role', 'owner')
            ->where('usage.0.label', 'Customers & suppliers')
            ->where('usage.0.count', 3));

    $this->get(route('admin.users.index', ['filter' => 'admins']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.id', $admin->id));

    $this->get(route('admin.users.show', $owner))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Show')
            ->where('user.email', 'owner@indus.test')
            ->where('user.is_you', false)
            ->has('companies', 1)
            ->where('companies.0.name', 'Indus Traders'));
});

test('a suspended company cannot be used until it is reactivated', function () {
    $admin = platformAdmin();
    [$owner, $team] = teamOwner();
    $invoice = issuedInvoice($this, $owner, $team, Company::factory()->for($team)->create());

    $event = CalendarEvent::factory()->for($team)->create([
        'organizer_id' => $owner->id,
        'starts_at' => '2026-09-30 12:10:00',
        'ends_at' => '2026-09-30 13:00:00',
        'reminder_minutes' => 15,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.companies.suspend', $team), ['reason' => 'The subscription has not been paid.'])
        ->assertSessionHasNoErrors();

    $team->refresh();

    expect($team->isSuspended())->toBeTrue()
        ->and($team->suspension_reason)->toBe('The subscription has not been paid.');

    // The workspace shows the suspension instead of the company's pages.
    $this->actingAs($owner)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/CompanySuspended')
            ->where('company', $team->name)
            ->where('reason', 'The subscription has not been paid.'));

    $this->get(route('invoices.index', ['current_team' => $team->slug]))->assertForbidden();

    // The account itself still works (e.g. to see or leave companies).
    $this->get(route('teams.index'))->assertOk();

    // Links sent to customers do not open, and no reminders go out.
    Auth::logout();
    $this->get(route('public.invoices.show', ['token' => $invoice->public_token]))->assertForbidden();
    $this->artisan('calendar:send-reminders')->assertSuccessful();

    expect($owner->notifications()->count())->toBe(0)
        ->and($event->fresh()->reminded_at)->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.companies.reactivate', $team))
        ->assertSessionHasNoErrors();

    expect($team->fresh()->isSuspended())->toBeFalse()
        ->and($team->fresh()->suspension_reason)->toBeNull();

    $this->actingAs($owner)->get(route('dashboard', ['current_team' => $team->slug]))->assertOk();

    Auth::logout();
    $this->get(route('public.invoices.show', ['token' => $invoice->public_token]))->assertOk();
});

test('admin rights can be given and taken, but not by an administrator from themselves', function () {
    $admin = platformAdmin();
    [$colleague] = teamOwner();
    $unconfirmed = User::factory()->unverified()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.grant', $colleague))
        ->assertSessionHasNoErrors();

    expect($colleague->fresh()->isPlatformAdmin())->toBeTrue();

    $this->delete(route('admin.users.revoke', $admin))->assertSessionHasErrors('user');

    expect($admin->fresh()->isPlatformAdmin())->toBeTrue();

    $this->delete(route('admin.users.revoke', $colleague))->assertSessionHasNoErrors();

    expect($colleague->fresh()->isPlatformAdmin())->toBeFalse();

    $this->post(route('admin.users.verify', $unconfirmed))->assertSessionHasNoErrors();

    expect($unconfirmed->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('the platform:admin command gives rights to an account or creates a new admin account', function () {
    [$owner] = teamOwner();
    $owner->update(['email' => 'abdul@example.test']);

    $this->artisan('platform:admin', ['email' => 'ABDUL@example.test'])
        ->expectsOutputToContain('is now a platform administrator')
        ->assertSuccessful();

    expect($owner->fresh()->isPlatformAdmin())->toBeTrue();

    $this->artisan('platform:admin', ['email' => 'founder@example.test'])
        ->expectsQuestion('Name', 'Platform Founder')
        ->expectsQuestion('Password', 'Secret!12345')
        ->expectsQuestion('Password again', 'Secret!12345')
        ->expectsOutputToContain('Platform administrator Platform Founder <founder@example.test> created')
        ->assertSuccessful();

    $founder = User::query()->where('email', 'founder@example.test')->sole();

    expect($founder->isPlatformAdmin())->toBeTrue()
        ->and($founder->hasVerifiedEmail())->toBeTrue()
        ->and($founder->currentTeam?->name)->toBe("Platform Founder's Team")
        ->and(password_verify('Secret!12345', $founder->password))->toBeTrue();

    // A new account cannot be created without someone typing the password.
    $this->artisan('platform:admin', ['email' => 'nobody@example.test', '--no-interaction' => true])->assertFailed();

    expect(User::query()->where('email', 'nobody@example.test')->exists())->toBeFalse();

    $this->artisan('platform:admin', ['--list' => true])
        ->expectsOutputToContain('founder@example.test')
        ->assertSuccessful();

    $this->artisan('platform:admin', ['email' => 'abdul@example.test', '--revoke' => true])->assertSuccessful();
    $this->artisan('platform:admin', ['email' => 'founder@example.test', '--revoke' => true])
        ->expectsOutputToContain('last platform administrator')
        ->assertFailed();

    expect($founder->fresh()->isPlatformAdmin())->toBeTrue();
});

test('nobody becomes a platform administrator by signing up, and the flag is not shown to others', function () {
    $this->post(route('register.store'), [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.test',
        'password' => 'Password!12345',
        'password_confirmation' => 'Password!12345',
        'is_platform_admin' => true,
    ]);

    $sneaky = User::query()->where('email', 'sneaky@example.test')->sole();

    expect($sneaky->isPlatformAdmin())->toBeFalse()
        ->and($sneaky->toArray())->not->toHaveKey('is_platform_admin');

    $admin = platformAdmin();

    expect($admin->toArray())->not->toHaveKey('is_platform_admin');
    expect(Team::query()->count())->toBe(2);
});
