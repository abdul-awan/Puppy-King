<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\PayComponentKind;
use App\Enums\PayslipLineSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An earning, deduction or employer contribution on a payslip.
 *
 * @property int $id
 * @property int $team_id
 * @property int $payslip_id
 * @property int|null $pay_component_id
 * @property int|null $employee_advance_id
 * @property PayslipLineSource $source
 * @property PayComponentKind $kind
 * @property string $name
 * @property string $amount
 * @property string $taxable_amount
 * @property int $sort
 * @property-read Payslip $payslip
 * @property-read PayComponent|null $component
 * @property-read EmployeeAdvance|null $advance
 */
#[Fillable(['payslip_id', 'pay_component_id', 'employee_advance_id', 'source', 'kind', 'name', 'amount', 'taxable_amount', 'sort'])]
class PayslipLine extends Model
{
    use BelongsToTeam;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /**
     * @return BelongsTo<PayComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'pay_component_id');
    }

    /**
     * @return BelongsTo<EmployeeAdvance, $this>
     */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PayslipLineSource::class,
            'kind' => PayComponentKind::class,
            'amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'sort' => 'integer',
        ];
    }
}
