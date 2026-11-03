<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An allowance, deduction or employer contribution on an employee's monthly salary: an amount,
 * or a percentage when the component is worked out as a percentage.
 *
 * @property int $id
 * @property int $team_id
 * @property int $employee_id
 * @property int $pay_component_id
 * @property string $value
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Employee $employee
 * @property-read PayComponent $component
 */
#[Fillable(['employee_id', 'pay_component_id', 'value', 'sort'])]
class EmployeePayItem extends Model
{
    use BelongsToTeam;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PayComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'pay_component_id');
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
            'sort' => 'integer',
        ];
    }
}
