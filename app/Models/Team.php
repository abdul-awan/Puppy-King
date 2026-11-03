<?php

namespace App\Models;

use App\Actions\Teams\SetUpTeamDefaults;
use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\TeamRole;
use App\Support\TeamSettings;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $suspended_at
 * @property string|null $suspension_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 */
#[Fillable(['name', 'slug', 'is_personal', 'settings'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });

        static::created(function (Team $team) {
            app(SetUpTeamDefaults::class)->handle($team);
        });

        // When a company is removed for good, its uploaded files go too — only once the database
        // transaction is committed, so a failed deletion never loses files.
        static::forceDeleted(function (Team $team) {
            DB::afterCommit(fn () => Storage::disk('local')->deleteDirectory("teams/{$team->id}"));
        });
    }

    /**
     * Determine if the platform administrators suspended the company.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Get the team's settings merged over the defaults.
     */
    public function teamSettings(): TeamSettings
    {
        return TeamSettings::for($this);
    }

    /**
     * Get the team's default department (company profile).
     */
    public function defaultDepartment(): ?Department
    {
        return $this->departments()->withoutGlobalScopes()->orderByDesc('is_default')->orderBy('id')->first();
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    /**
     * @return HasMany<PaymentTerm, $this>
     */
    public function paymentTerms(): HasMany
    {
        return $this->hasMany(PaymentTerm::class);
    }

    /**
     * @return HasMany<WorkType, $this>
     */
    public function workTypes(): HasMany
    {
        return $this->hasMany(WorkType::class);
    }

    /**
     * @return HasMany<EmailTemplate, $this>
     */
    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * @return HasMany<CustomField, $this>
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    /**
     * @return HasMany<DealPipeline, $this>
     */
    public function dealPipelines(): HasMany
    {
        return $this->hasMany(DealPipeline::class);
    }

    /**
     * @return HasMany<DealSource, $this>
     */
    public function dealSources(): HasMany
    {
        return $this->hasMany(DealSource::class);
    }

    /**
     * @return HasMany<LostReason, $this>
     */
    public function lostReasons(): HasMany
    {
        return $this->hasMany(LostReason::class);
    }

    /**
     * @return HasMany<ExpenseCategory, $this>
     */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    /**
     * @return HasMany<DailyExpenseCategory, $this>
     */
    public function dailyExpenseCategories(): HasMany
    {
        return $this->hasMany(DailyExpenseCategory::class);
    }

    /**
     * @return HasMany<PayComponent, $this>
     */
    public function payComponents(): HasMany
    {
        return $this->hasMany(PayComponent::class);
    }

    /**
     * @return HasMany<SalaryTaxTable, $this>
     */
    public function salaryTaxTables(): HasMany
    {
        return $this->hasMany(SalaryTaxTable::class);
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'settings' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
