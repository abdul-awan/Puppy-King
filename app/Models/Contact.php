<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAddresses;
use App\Concerns\HasAttachments;
use App\Concerns\HasCustomFields;
use App\Concerns\HasNotes;
use App\Concerns\HasTags;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Enums\CustomFieldEntity;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A person, optionally linked to one or more companies.
 *
 * @property int $id
 * @property int $team_id
 * @property string|null $salutation
 * @property string|null $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $cnic
 * @property Carbon|null $birthdate
 * @property string $language
 * @property int|null $responsible_user_id
 * @property bool $marketing_consent
 * @property string|null $remarks
 * @property array<string, mixed>|null $custom_fields
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read Collection<int, Company> $companies
 * @property-read User|null $responsibleUser
 */
#[Fillable([
    'salutation', 'first_name', 'last_name', 'email', 'phone', 'mobile', 'cnic', 'birthdate',
    'language', 'responsible_user_id', 'marketing_consent', 'remarks', 'custom_fields', 'archived_at',
])]
class Contact extends Model implements Attachable
{
    /** @use HasFactory<ContactFactory> */
    use BelongsToTeam, HasAddresses, HasAttachments, HasCustomFields, HasFactory, HasNotes, HasTags, LogsActivity, SoftDeletes;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['full_name'];

    /**
     * Get the custom field entity type of the model.
     */
    public static function customFieldEntity(): CustomFieldEntity
    {
        return CustomFieldEntity::Contact;
    }

    /**
     * Get the contact's full name.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim(implode(' ', array_filter([$this->first_name, $this->last_name]))));
    }

    /**
     * Get the companies the contact is linked to.
     *
     * @return BelongsToMany<Company, $this, CompanyContact, 'pivot'>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->using(CompanyContact::class)
            ->withPivot(['id', 'position', 'is_primary', 'is_decision_maker'])
            ->withTimestamps();
    }

    /**
     * Get the account manager of the contact.
     *
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Determine if the contact is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Scope a query to contacts matching a search term.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        // Every word must match one of the fields, so "Ali Khan" finds first name Ali + last name Khan.
        foreach (preg_split('/\s+/', trim($term)) ?: [] as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(fn (Builder $query) => $query
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('mobile', 'like', $like));
        }
    }

    /**
     * Scope a query to contacts that are not archived.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'marketing_consent' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }
}
