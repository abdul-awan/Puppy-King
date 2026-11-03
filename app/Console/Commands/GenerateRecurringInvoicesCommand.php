<?php

namespace App\Console\Commands;

use App\Actions\Invoicing\GenerateRecurringInvoice;
use App\Models\RecurringInvoice;
use App\Models\Team;
use App\Support\CurrentTeam;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('invoices:generate-recurring {--date= : Generate as if today were this date (Y-m-d)}')]
#[Description('Create the invoices of recurring invoice profiles that are due')]
class GenerateRecurringInvoicesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GenerateRecurringInvoice $generate, CurrentTeam $currentTeam): int
    {
        $today = $this->option('date') ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay() : CarbonImmutable::today();

        $profiles = RecurringInvoice::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotNull('next_date')
            ->whereDate('next_date', '<=', $today)
            // Suspended companies are skipped; their missed invoices follow once reactivated.
            ->whereHas('team', fn ($query) => $query->whereNull('suspended_at'))
            ->orderBy('team_id')
            ->orderBy('id')
            ->get();

        if ($profiles->isEmpty()) {
            $this->components->info('No recurring invoices are due.');

            return self::SUCCESS;
        }

        foreach ($profiles as $profile) {
            $currentTeam->set(Team::query()->find($profile->team_id));

            try {
                $created = 0;

                while ($profile->isDue($today) && $created < RecurringInvoice::MAX_CATCH_UP) {
                    $invoice = $generate->handle($profile);
                    $profile->refresh();
                    $created++;

                    $this->components->twoColumnDetail(
                        "{$profile->title} (team {$profile->team_id})",
                        ($invoice->number ?? 'draft').' for '.$invoice->date->format('d M Y'),
                    );
                }
            } catch (Throwable $e) {
                report($e);
                $this->components->error("Recurring invoice {$profile->id} failed: {$e->getMessage()}");
            } finally {
                $currentTeam->set(null);
            }
        }

        return self::SUCCESS;
    }
}
