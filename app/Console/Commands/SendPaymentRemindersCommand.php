<?php

namespace App\Console\Commands;

use App\Actions\Invoicing\SendPaymentReminder;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('invoices:send-reminders')]
#[Description('E-mail payment reminders for overdue invoices of teams that switched reminders on')]
class SendPaymentRemindersCommand extends Command
{
    /**
     * Execute the console command.
     *
     * Each team sets the reminder days, e.g. [3, 10, 30]: the first reminder goes out
     * 3 days after the due date, the second after 10 days and the last after 30 days.
     */
    public function handle(SendPaymentReminder $reminder, CurrentTeam $currentTeam): int
    {
        $sent = 0;

        foreach (Team::query()->whereNull('suspended_at')->cursor() as $team) {
            $settings = $team->teamSettings();
            $stages = array_values(array_unique(array_map('intval', (array) $settings->get('reminders.days_after_due', []))));
            sort($stages);

            if (! $settings->get('reminders.enabled') || $stages === []) {
                continue;
            }

            $sender = $team->owner();

            if (! $sender instanceof User) {
                continue;
            }

            $currentTeam->set($team);

            try {
                $invoices = Invoice::query()
                    ->where('status', InvoiceStatus::Open)
                    ->whereNotNull('customer_email')
                    ->whereDate('due_date', '<', today())
                    ->get();

                foreach ($invoices as $invoice) {
                    $stage = $stages[$invoice->reminder_count] ?? null;

                    if ($stage === null || $invoice->daysOverdue() < $stage || $invoice->last_reminder_at?->isToday()) {
                        continue;
                    }

                    $email = $reminder->send($invoice, $sender);
                    $sent += $email->status === 'sent' ? 1 : 0;

                    $this->components->twoColumnDetail("{$team->name}: {$invoice->number}", "{$email->status} to {$email->to}");
                }
            } catch (Throwable $e) {
                report($e);
                $this->components->error("Reminders for team {$team->id} failed: {$e->getMessage()}");
            } finally {
                $currentTeam->set(null);
            }
        }

        $this->components->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
