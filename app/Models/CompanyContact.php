<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The link between a company and a contact person.
 *
 * @property int $id
 * @property int $company_id
 * @property int $contact_id
 * @property string|null $position
 * @property bool $is_primary
 * @property bool $is_decision_maker
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CompanyContact extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'company_contact';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_decision_maker' => 'boolean',
        ];
    }
}
