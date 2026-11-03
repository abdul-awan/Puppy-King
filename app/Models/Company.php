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
use App\Enums\BusinessType;
use App\Enums\CompanyRelationship;
use App\Enums\CustomFieldEntity;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer, prospect, supplier or partner organisation.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property CompanyRelationship $relationship
 * @property BusinessType|null $business_type
 * @property string|null $ntn
 * @property string|null $strn
 * @property string|null $cnic
 * @property bool $sales_tax_registered
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $website
 * @property string|null $industry
 * @property int|null $responsible_user_id
 * @property int|null $payment_term_id
 * @property string|null $currency
 * @property string $language
 * @property string|null $iban
 * @property string|null $remarks
 * @property array<string, mixed>|null $custom_fields
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Contact> $contacts
 * @property-read User|null $responsibleUser
 * @property-read PaymentTerm|null $paymentTerm
 */
#[Fillable([
    'name', 'relationship', 'business_type', 'ntn', 'strn', 'cnic', 'sales_tax_registered',
    'email', 'phone', 'mobile', 'website', 'industry', 'responsible_user_id', 'payment_term_id',
    'currency', 'language', 'iban', 'remarks', 'custom_fields', 'archived_at',
])]
class Company extends Model implements Attachable
{
    /** @use HasFactory<CompanyFactory> */
    use BelongsToTeam, HasAddresses, HasAttachments, HasCustomFields, HasFactory, HasNotes, HasTags, LogsActivity, SoftDeletes;

    /**
     * Get the custom field entity type of the model.
     */
    public static function customFieldEntity(): CustomFieldEntity
    {
        return CustomFieldEntity::Company;
    }

    /**
     * Get the people linked to the company.
     *
     * @return BelongsToMany<Contact, $this, CompanyContact, 'pivot'>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
            ->using(CompanyContact::class)
            ->withPivot(['id', 'position', 'is_primary', 'is_decision_maker'])
            ->withTimestamps();
    }

    /**
     * Get the account manager of the company.
     *
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Get the company's default payment term.
     *
     * @return BelongsTo<PaymentTerm, $this>
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    /**
     * Get the company's primary contact person, if any.
     */
    public function primaryContact(): ?Contact
    {
        return $this->contacts()
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('contacts.id')
            ->first();
    }

    /**
     * Determine if the company is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Scope a query to companies matching a search term.
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
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('mobile', 'like', $like)
            ->orWhere('ntn', 'like', $like)
            ->orWhere('strn', 'like', $like));
    }

    /**
     * Scope a query to companies that are not archived.
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
            'relationship' => CompanyRelationship::class,
            'business_type' => BusinessType::class,
            'sales_tax_registered' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }
}
