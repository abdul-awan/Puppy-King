<?php

namespace App\Console\Commands;

use App\Actions\Teams\SetUpTeamDefaults;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('teams:set-up-defaults {team? : The id or slug of a single team}')]
#[Description('Create any missing default records (company profile, tax rates, pipeline...) for existing teams')]
class SetUpTeamDefaultsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SetUpTeamDefaults $setUp): int
    {
        $teams = Team::query()
            ->when($this->argument('team'), fn ($query, $team) => $query->where('id', $team)->orWhere('slug', $team))
            ->get();

        foreach ($teams as $team) {
            $setUp->handle($team);
            $this->components->info("Defaults checked for {$team->name}.");
        }

        if ($teams->isEmpty()) {
            $this->components->warn('No teams found.');
        }

        return self::SUCCESS;
    }
}
