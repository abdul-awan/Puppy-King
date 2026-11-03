<?php

use App\Enums\DealStatus;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Deal;
use App\Models\DealPhase;
use App\Models\DealPipeline;
use App\Models\LostReason;
use App\Models\Team;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Get the phases of the team's default pipeline, in order.
 *
 * @return Collection<int, DealPhase>
 */
function teamPhases(Team $team): Collection
{
    $pipeline = DealPipeline::withoutGlobalScopes()->where('team_id', $team->id)->where('is_default', true)->firstOrFail();

    return DealPhase::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->orderBy('sort')->get();
}

test('new teams get a sales pipeline with phases', function () {
    [, $team] = teamOwner();

    expect(teamPhases($team)->pluck('name')->all())
        ->toBe(['New lead', 'Contacted', 'Qualified', 'Proposal sent', 'Negotiation']);
});

test('a deal can be created for a company', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $phase = teamPhases($team)->first();

    $this->actingAs($user)
        ->post(route('deals.store', ['current_team' => $team->slug]), [
            'title' => '50 laptops',
            'company_id' => $company->id,
            'pipeline_id' => $phase->pipeline_id,
            'phase_id' => $phase->id,
            'value' => '14250000',
            'currency' => 'PKR',
            'expected_close_date' => '2026-12-31',
        ])
        ->assertRedirect();

    $deal = Deal::withoutGlobalScopes()->where('title', '50 laptops')->firstOrFail();

    expect($deal->team_id)->toBe($team->id)
        ->and($deal->status)->toBe(DealStatus::Open)
        ->and($deal->effectiveProbability())->toBe(10)
        ->and($deal->weightedValue())->toBe(1425000.0);
});

test('a deal needs a company or a contact', function () {
    [$user, $team] = teamOwner();
    $phase = teamPhases($team)->first();

    $this->actingAs($user)
        ->post(route('deals.store', ['current_team' => $team->slug]), [
            'title' => 'Orphan',
            'pipeline_id' => $phase->pipeline_id,
            'phase_id' => $phase->id,
            'value' => '1',
            'currency' => 'PKR',
        ])
        ->assertSessionHasErrors('company_id');
});

test('the phase must belong to the chosen pipeline', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $ownPhase = teamPhases($team)->first();
    $foreignPhase = teamPhases($otherTeam)->first();

    $this->actingAs($user)
        ->post(route('deals.store', ['current_team' => $team->slug]), [
            'title' => 'Sneaky',
            'company_id' => $company->id,
            'pipeline_id' => $ownPhase->pipeline_id,
            'phase_id' => $foreignPhase->id,
            'value' => '1',
            'currency' => 'PKR',
        ])
        ->assertSessionHasErrors('phase_id');
});

test('the board groups open deals by phase with totals', function () {
    [$user, $team] = teamOwner();
    $phases = teamPhases($team);

    Deal::factory()->forTeam($team)->create(['phase_id' => $phases[0]->id, 'value' => 1000]);
    Deal::factory()->forTeam($team)->create(['phase_id' => $phases[0]->id, 'value' => 3000]);
    Deal::factory()->forTeam($team)->create(['phase_id' => $phases[2]->id, 'value' => 5000]);
    Deal::factory()->forTeam($team)->create(['phase_id' => $phases[2]->id, 'value' => 9999, 'status' => DealStatus::Won]);

    $this->actingAs($user)
        ->get(route('deals.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('deals/Index')
            ->has('board', 5)
            ->where('board.0.count', 2)
            ->where('board.0.total', 4000)
            ->where('board.0.weighted', 400)
            ->where('board.2.count', 1)
            ->where('board.2.weighted', 2000));
});

test('a deal can be moved between phases and it is logged', function () {
    [$user, $team] = teamOwner();
    $phases = teamPhases($team);
    $deal = Deal::factory()->forTeam($team)->create(['phase_id' => $phases[0]->id]);
    $other = Deal::factory()->forTeam($team)->create(['phase_id' => $phases[1]->id, 'sort_order' => 0]);

    $this->actingAs($user)
        ->patch(route('deals.move', ['current_team' => $team->slug, 'deal' => $deal->id]), [
            'phase_id' => $phases[1]->id,
            'position' => 0,
        ])
        ->assertRedirect();

    expect($deal->fresh()->phase_id)->toBe($phases[1]->id)
        ->and($deal->fresh()->sort_order)->toBe(0)
        ->and($other->fresh()->sort_order)->toBe(1);

    $log = ActivityLog::withoutGlobalScopes()->where('subject_id', $deal->id)->where('event', 'moved')->firstOrFail();

    expect($log->description)->toBe('Moved from New lead to Contacted');
});

test('deals can be won, lost with a reason and reopened', function () {
    [$user, $team] = teamOwner();
    $deal = Deal::factory()->forTeam($team)->create();
    $reason = LostReason::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Price too high')->firstOrFail();
    $params = ['current_team' => $team->slug, 'deal' => $deal->id];

    $this->actingAs($user)->post(route('deals.won', $params))->assertRedirect();
    expect($deal->fresh()->status)->toBe(DealStatus::Won)
        ->and($deal->fresh()->closed_at)->not->toBeNull();

    $this->post(route('deals.reopen', $params))->assertRedirect();
    expect($deal->fresh()->status)->toBe(DealStatus::Open);

    $this->post(route('deals.lost', $params), ['lost_reason_id' => $reason->id, 'lost_note' => 'Went with a cheaper vendor'])
        ->assertRedirect();

    $deal->refresh();

    expect($deal->status)->toBe(DealStatus::Lost)
        ->and($deal->lost_reason_id)->toBe($reason->id)
        ->and($deal->effectiveProbability())->toBe(0);

    expect(ActivityLog::withoutGlobalScopes()->where('subject_id', $deal->id)->where('event', 'lost')->value('description'))
        ->toBe('Marked as lost: Price too high');
});

test('deals of another team cannot be moved', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();
    $foreign = Deal::factory()->forTeam($otherTeam)->create();

    $this->actingAs($user)
        ->patch(route('deals.move', ['current_team' => $team->slug, 'deal' => $foreign->id]), ['phase_id' => teamPhases($team)->first()->id])
        ->assertNotFound();
});

test('pipelines and phases can be managed in the settings', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('settings.pipelines.store', ['current_team' => $team->slug]), ['name' => 'Tenders'])
        ->assertRedirect();

    $pipeline = DealPipeline::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Tenders')->firstOrFail();

    $this->post(route('settings.pipelines.phases.store', ['current_team' => $team->slug, 'pipeline' => $pipeline->id]), [
        'name' => 'Bid submitted',
        'probability' => 50,
    ])->assertRedirect();

    $phases = DealPhase::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->orderBy('sort')->get();
    expect($phases->pluck('name')->all())->toBe(['New', 'Bid submitted']);

    $this->put(route('settings.pipelines.phases.order', ['current_team' => $team->slug, 'pipeline' => $pipeline->id]), [
        'phase_ids' => [$phases[1]->id, $phases[0]->id],
    ])->assertRedirect();

    expect(DealPhase::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->orderBy('sort')->pluck('name')->all())
        ->toBe(['Bid submitted', 'New']);

    $this->get(route('settings.pipelines.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page->component('company-settings/Pipelines')->has('pipelines', 2));
});

test('a phase with deals cannot be deleted', function () {
    [$user, $team] = teamOwner();
    $phase = teamPhases($team)->first();
    Deal::factory()->forTeam($team)->create(['phase_id' => $phase->id]);

    $this->actingAs($user)
        ->delete(route('settings.phases.destroy', ['current_team' => $team->slug, 'phase' => $phase->id]))
        ->assertSessionHasErrors('phase');
});
