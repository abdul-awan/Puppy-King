<?php

namespace App\Http\Controllers\Expenses;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpensePaymentRequest;
use App\Models\Expense;
use App\Models\ExpensePayment;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExpensePaymentController extends Controller
{
    /**
     * Record a payment to the supplier.
     */
    public function store(ExpensePaymentRequest $request, Expense $expense): RedirectResponse
    {
        DB::transaction(function () use ($request, $expense) {
            $expense = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($expense->status !== ExpenseStatus::Approved) {
                throw ValidationException::withMessages(['amount' => 'Approve the expense before paying it.']);
            }

            $amount = BigDecimal::of((string) $request->validated('amount'))->toScale(2);

            if ($amount->isGreaterThan(BigDecimal::of($expense->balanceDue()))) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('The payment is more than what is still to be paid (%s %s).', $expense->currency, number_format((float) $expense->balanceDue(), 2)),
                ]);
            }

            $payment = new ExpensePayment([...$request->safe()->except('amount'), 'amount' => (string) $amount]);
            $payment->team_id = $expense->team_id;
            $payment->expense_id = $expense->id;
            $payment->user_id = $request->user()->id;
            $payment->save();

            $expense->refreshBalance();
            $expense->logActivity('payment', sprintf('Paid %s %s', $expense->currency, number_format((float) (string) $amount, 2)));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    /**
     * Remove a payment (e.g. entered by mistake).
     */
    public function destroy(Expense $expense, ExpensePayment $payment): RedirectResponse
    {
        abort_unless($payment->expense_id === $expense->id, 404);

        DB::transaction(function () use ($expense, $payment) {
            $payment->delete();

            $expense->refreshBalance();
            $expense->logActivity('payment_removed', sprintf('Payment of %s %s from %s removed', $expense->currency, number_format((float) $payment->amount, 2), $payment->date->format('d M Y')));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment removed.')]);

        return back();
    }
}
