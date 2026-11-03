<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\PaymentMethod;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment received for an invoice.
 *
 * In Pakistan the customer (as withholding agent) often deducts income tax and part
 * of the sales tax before paying. Those withheld amounts settle the invoice too, so
 * the settled amount is the money received plus both withholdings.
 *
 * @property int $id
 * @property int $team_id
 * @property int $invoice_id
 * @property CarbonImmutable $date
 * @property string $amount
 * @property string $income_tax_withheld
 * @property string $sales_tax_withheld
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property string|null $notes
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Invoice $invoice
 * @property-read User|null $user
 */
#[Fillable(['invoice_id', 'date', 'amount', 'income_tax_withheld', 'sales_tax_withheld', 'method', 'reference', 'notes', 'user_id'])]
class InvoicePayment extends Model
{
    use BelongsToTeam;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the part of the invoice this payment settles (money received plus withholdings).
     */
    public function settledAmount(): BigDecimal
    {
        return BigDecimal::of($this->amount)
            ->plus(BigDecimal::of($this->income_tax_withheld))
            ->plus(BigDecimal::of($this->sales_tax_withheld));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'income_tax_withheld' => 'decimal:2',
            'sales_tax_withheld' => 'decimal:2',
            'method' => PaymentMethod::class,
        ];
    }
}
