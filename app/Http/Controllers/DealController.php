<?php

namespace App\Http\Controllers;

use App\Enums\DealStatus;
use App\Http\Requests\DealRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CustomFieldResource;
use App\Http\Resources\DealResource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealPhase;
use App\Models\DealPipeline;
use App\Models\DealSource;
use App\Models\LostReason;
use App\Support\CurrentTeam;
use App\Support\RelatedDocuments;
use App\Support\TeamOptions;
use App\Support\TeamRule;
use App\Support\Timeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    /**
     * Show the deals as a board (per phase) or as a list.
     */
    public function index(Request $request): Response
    {
        $pipelines = DealPipeline::query()->with('phases')->orderByDesc('is_default')->orderBy('sort')->get();
        $pipeline = $pipelines->firstWhere('id', $request->integer('pipeline')) ?? $pipelines->first();

        $filters = [
            'view' => $request->input('view') === 'list' ? 'list' : 'board',
            'pipeline' => $pipeline?->id,
            'search' => $request->string('search')->trim()->toString(),
            'responsible' => $request->integer('responsible') ?: null,
            'status' => in_array($request->input('status'), ['open', 'won', 'lost'], true) ? (string) $request->input('status') : 'open',
        ];

        $base = fn (): Builder => Deal::query()
            ->with(['phase', 'company:id,name', 'contact:id,first_name,last_name', 'responsibleUser:id,name'])
            ->when($pipeline, fn ($query) => $query->where('pipeline_id', $pipeline?->id))
            ->search($filters['search'])
            ->when($filters['responsible'], fn ($query, $value) => $query->where('responsible_user_id', $value));

        $board = null;
        $list = null;

        if ($filters['view'] === 'board' && $pipeline) {
            $deals = $base()->where('status', DealStatus::Open)->orderBy('sort_order')->orderBy('id')->get()->groupBy('phase_id');

            $board = $pipeline->phases->map(function (DealPhase $phase) use ($deals) {
                $phaseDeals = $deals->get($phase->id, collect());

                return [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'probability' => $phase->probability,
                    'count' => $phaseDeals->count(),
                    'total' => round((float) $phaseDeals->sum(fn (Deal $deal) => (float) $deal->value), 2),
                    'weighted' => round((float) $phaseDeals->sum(fn (Deal $deal) => $deal->weightedValue()), 2),
                    'deals' => DealResource::collection($phaseDeals->values()),
                ];
            })->values();
        } else {
            $list = DealResource::collection(
                $base()->where('status', $filters['status'])->latest('id')->paginate(25)->withQueryString(),
            );
        }

        return Inertia::render('deals/Index', [
            'filters' => $filters,
            'pipelines' => $pipelines->map(fn (DealPipeline $item) => ['id' => $item->id, 'name' => $item->name])->values(),
            'board' => $board,
            'deals' => $list,
            'users' => TeamOptions::users(),
        ]);
    }

    /**
     * Show the form for creating a new deal.
     */
    public function create(Request $request): Response
    {
        $company = $request->integer('company') ? Company::query()->find($request->integer('company')) : null;
        $contact = $request->integer('contact') ? Contact::query()->find($request->integer('contact')) : null;

        return Inertia::render('deals/Create', [
            'prefill' => [
                'company' => $company?->only(['id', 'name']),
                'contact' => $contact ? ['id' => $contact->id, 'name' => $contact->full_name] : null,
                'pipeline_id' => $request->integer('pipeline') ?: null,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a newly created deal.
     */
    public function store(DealRequest $request): RedirectResponse
    {
        $deal = DB::transaction(function () use ($request) {
            $deal = Deal::create([
                ...$request->safe()->except('tag_ids'),
                'status' => DealStatus::Open,
                'sort_order' => (int) Deal::query()->where('phase_id', $request->validated('phase_id'))->max('sort_order') + 1,
            ]);

            $deal->syncTags($request->validated('tag_ids'));

            return $deal;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deal created.')]);

        return to_route('deals.show', $deal);
    }

    /**
     * Display the deal.
     */
    public function show(Request $request, Deal $deal): Response
    {
        $deal->load(['pipeline.phases', 'phase', 'company:id,name', 'contact:id,first_name,last_name', 'responsibleUser:id,name', 'source:id,name', 'lostReason:id,name', 'tags']);

        return Inertia::render('deals/Show', [
            'deal' => DealResource::make($deal),
            'phases' => $deal->pipeline->phases->map(fn (DealPhase $phase) => $phase->only(['id', 'name', 'probability']))->values(),
            'lostReasons' => LostReason::query()->orderBy('sort')->get(['id', 'name']),
            'timeline' => Timeline::for($deal),
            'attachments' => AttachmentResource::collection($deal->attachments()->with('user:id,name')->get()),
            'customFields' => CustomFieldResource::collection(Deal::customFieldDefinitions()),
            'related' => RelatedDocuments::for($request->user(), ['deal_id' => $deal->id]),
        ]);
    }

    /**
     * Show the form for editing the deal.
     */
    public function edit(Deal $deal): Response
    {
        $deal->load(['company:id,name', 'contact:id,first_name,last_name', 'tags']);

        return Inertia::render('deals/Edit', [
            'deal' => DealResource::make($deal),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the deal.
     */
    public function update(DealRequest $request, Deal $deal): RedirectResponse
    {
        DB::transaction(function () use ($request, $deal) {
            $deal->update($request->safe()->except('tag_ids'));
            $deal->syncTags($request->validated('tag_ids'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deal updated.')]);

        return to_route('deals.show', $deal);
    }

    /**
     * Move the deal to another phase and/or position on the board.
     */
    public function move(Request $request, Deal $deal): RedirectResponse
    {
        $validated = $request->validate([
            'phase_id' => ['required', 'integer', TeamRule::exists('deal_phases')->where('pipeline_id', $deal->pipeline_id)],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $from = $deal->phase;

        DB::transaction(function () use ($deal, $validated) {
            $siblings = Deal::query()
                ->where('phase_id', $validated['phase_id'])
                ->where('status', DealStatus::Open)
                ->whereKeyNot($deal->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            array_splice($siblings, min($validated['position'] ?? count($siblings), count($siblings)), 0, [$deal->id]);

            foreach ($siblings as $index => $id) {
                Deal::query()->whereKey($id)->update(['sort_order' => $index]);
            }

            $deal->forceFill(['phase_id' => $validated['phase_id']]);
            $deal->saveQuietly();
        });

        if ($from->id !== (int) $validated['phase_id']) {
            $to = DealPhase::query()->findOrFail((int) $validated['phase_id']);
            $deal->logActivity('moved', "Moved from {$from->name} to {$to->name}", ['from' => $from->id, 'to' => $to->id]);
        }

        return back();
    }

    /**
     * Mark the deal as won.
     */
    public function won(Deal $deal): RedirectResponse
    {
        $deal->forceFill(['status' => DealStatus::Won, 'closed_at' => now(), 'lost_reason_id' => null, 'lost_note' => null]);
        $deal->saveQuietly();
        $deal->logActivity('won', 'Marked as won');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Congratulations, the deal is won!')]);

        return back();
    }

    /**
     * Mark the deal as lost.
     */
    public function lost(Request $request, Deal $deal): RedirectResponse
    {
        $validated = $request->validate([
            'lost_reason_id' => ['nullable', 'integer', TeamRule::exists('lost_reasons')],
            'lost_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $deal->forceFill([
            'status' => DealStatus::Lost,
            'closed_at' => now(),
            'lost_reason_id' => $validated['lost_reason_id'] ?? null,
            'lost_note' => $validated['lost_note'] ?? null,
        ]);
        $deal->saveQuietly();

        $reason = $deal->lostReason?->name;
        $deal->logActivity('lost', 'Marked as lost'.($reason ? ": {$reason}" : ''), ['note' => $validated['lost_note'] ?? null]);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Deal marked as lost.')]);

        return back();
    }

    /**
     * Reopen a won or lost deal.
     */
    public function reopen(Deal $deal): RedirectResponse
    {
        $deal->forceFill(['status' => DealStatus::Open, 'closed_at' => null, 'lost_reason_id' => null, 'lost_note' => null]);
        $deal->saveQuietly();
        $deal->logActivity('reopened', 'Reopened');

        return back();
    }

    /**
     * Delete the deal.
     */
    public function destroy(Deal $deal): RedirectResponse
    {
        $deal->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deal deleted.')]);

        return to_route('deals.index');
    }

    /**
     * Get the option lists for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $team = app(CurrentTeam::class)->team();

        return [
            'pipelines' => DealPipeline::query()
                ->with('phases')
                ->orderByDesc('is_default')
                ->orderBy('sort')
                ->get()
                ->map(fn (DealPipeline $pipeline) => [
                    'id' => $pipeline->id,
                    'name' => $pipeline->name,
                    'phases' => $pipeline->phases->map(fn (DealPhase $phase) => $phase->only(['id', 'name', 'probability']))->values(),
                ]),
            'sources' => DealSource::query()->orderBy('sort')->get(['id', 'name']),
            'users' => TeamOptions::users(),
            'departments' => TeamOptions::departments(),
            'currencies' => TeamOptions::currencies(),
            'defaultCurrency' => $team->teamSettings()->currency(),
            'tags' => TeamOptions::tags(),
            'customFields' => CustomFieldResource::collection(Deal::customFieldDefinitions()),
        ];
    }
}
