<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAttachments;
use App\Concerns\HasCustomFields;
use App\Concerns\HasNotes;
use App\Concerns\HasTags;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Enums\CustomFieldEntity;
use App\Enums\DealStatus;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A sales opportunity moving through the phases of a pipeline.
 *
 * @property int $id
 * @property int $team_id
 * @property int $pipeline_id
 * @property int $phase_id
 * @property string $title
 * @property int|null $company_id
 * @property int|null $contact_id
 * @property int|null $responsible_user_id
 * @property int|null $source_id
 * @property int|null $department_id
 * @property string $value
 * @property string $currency
 * @property int|null $probability
 * @property Carbon|null $expected_close_date
 * @property DealStatus $status
 * @property Carbon|null $closed_at
 * @property int|null $lost_reason_id
 * @property string|null $lost_note
 * @property string|null $description
 * @property array<string, mixed>|null $custom_fields
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read DealPipeline $pipeline
 * @property-read DealPhase $phase
 * @property-read Company|null $company
 * @property-read Contact|null $contact
 * @property-read User|null $responsibleUser
 * @property-read DealSource|null $source
 * @property-read LostReason|null $lostReason
 */
#[Fillable([
    'pipeline_id', 'phase_id', 'title', 'company_id', 'contact_id', 'responsible_user_id', 'source_id',
    'department_id', 'value', 'currency', 'probability', 'expected_close_date', 'status', 'closed_at',
    'lost_reason_id', 'lost_note', 'description', 'custom_fields', 'sort_order',
])]
class Deal extends Model implements Attachable
{
    /** @use HasFactory<DealFactory> */
    use BelongsToTeam, HasAttachments, HasCustomFields, HasFactory, HasNotes, HasTags, LogsActivity, SoftDeletes;

    /**
     * Get the custom field entity type of the model.
     */
    public static function customFieldEntity(): CustomFieldEntity
    {
        return CustomFieldEntity::Deal;
    }

    /**
     * @return BelongsTo<DealPipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(DealPipeline::class, 'pipeline_id');
    }

    /**
     * @return BelongsTo<DealPhase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(DealPhase::class, 'phase_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return BelongsTo<DealSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(DealSource::class, 'source_id');
    }

    /**
     * @return BelongsTo<LostReason, $this>
     */
    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LostReason::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the probability that applies: the deal's own or its phase's.
     */
    public function effectiveProbability(): int
    {
        return match ($this->status) {
            DealStatus::Won => 100,
            DealStatus::Lost => 0,
            DealStatus::Open => $this->probability ?? $this->phase->probability,
        };
    }

    /**
     * Get the value weighted by the probability.
     */
    public function weightedValue(): float
    {
        return round((float) $this->value * $this->effectiveProbability() / 100, 2);
    }

    /**
     * Get the name of the customer (company or contact).
     */
    public function customerName(): ?string
    {
        return $this->company->name ?? $this->contact?->full_name;
    }

    /**
     * Scope a query to deals matching a search term.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        $query->where(fn (Builder $query) => $query
            ->where('title', 'like', $like)
            ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'like', $like))
            ->orWhereHas('contact', fn (Builder $contact) => $contact->where('last_name', 'like', $like)->orWhere('first_name', 'like', $like)));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'status' => DealStatus::class,
            'closed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }
}
