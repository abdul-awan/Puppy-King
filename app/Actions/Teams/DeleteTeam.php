<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Deletes a company (workspace) for good, together with all its data: customers, contacts, deals,
 * quotations, invoices, payments, expenses, daily expenses, employees and payrolls, products,
 * projects, time, tickets, work orders, calendar, settings, history, notifications and uploaded files.
 *
 * The people keep their accounts. Whoever had this company open moves to another one of theirs;
 * whoever is left without any company gets a new, empty one (an account needs a company to work in).
 * Everything happens in one database transaction: if anything fails, nothing is deleted.
 */
class DeleteTeam
{
    public function __construct(private CreateTeam $createTeam)
    {
        //
    }

    /**
     * Delete the company and return how many records were deleted.
     */
    public function handle(Team $team, ?User $by = null): int
    {
        $members = $team->members()->get();
        $tables = $this->tablesInDeleteOrder();
        $teamId = $team->id;

        $deleted = DB::transaction(function () use ($team, $teamId, $members, $tables, $by) {
            $deleted = 0;

            foreach ($tables as $table) {
                $deleted += DB::table($table)->where('team_id', $teamId)->delete();
            }

            // Notifications belong to people, but tell about this company.
            DB::table('notifications')->where('data->team_id', $teamId)->delete();

            // Removes the company itself; its files go once the transaction is committed.
            $team->forceDelete();

            $this->moveMembers($members, $teamId, $by);

            return $deleted;
        });

        Log::notice('Company deleted with all its data.', [
            'team_id' => $teamId,
            'name' => $team->name,
            'slug' => $team->slug,
            'records' => $deleted,
            'deleted_by' => $by?->id,
        ]);

        return $deleted;
    }

    /**
     * Every table holding company data (a team_id column), in an order in which they can be emptied:
     * a table that refers to another company table is emptied before that table.
     *
     * @return list<string>
     */
    public function tablesInDeleteOrder(): array
    {
        $tables = array_values(array_filter(
            array_map(fn (array $table) => (string) $table['name'], Schema::getTables()),
            fn (string $table) => $table !== 'teams' && Schema::hasColumn($table, 'team_id'),
        ));

        // The company tables each table refers to.
        $references = [];

        foreach ($tables as $table) {
            $references[$table] = array_values(array_unique(array_filter(
                array_map(fn (array $key) => (string) $key['foreign_table'], Schema::getForeignKeys($table)),
                fn (string $other) => $other !== $table && in_array($other, $tables, true),
            )));
        }

        $ordered = [];
        $remaining = $tables;

        while ($remaining !== []) {
            // Tables no remaining table refers to can be emptied now.
            $free = array_values(array_filter($remaining, function (string $table) use ($remaining, $references) {
                foreach ($remaining as $other) {
                    if ($other !== $table && in_array($table, $references[$other], true)) {
                        return false;
                    }
                }

                return true;
            }));

            if ($free === []) {
                throw new RuntimeException('There is no safe order to delete the company data in; these tables refer to each other: '.implode(', ', $remaining).'.');
            }

            array_push($ordered, ...$free);
            $remaining = array_values(array_diff($remaining, $free));
        }

        return $ordered;
    }

    /**
     * Give the former members a company to work in.
     *
     * @param  Collection<int, User>  $members
     */
    protected function moveMembers(Collection $members, int $teamId, ?User $by): void
    {
        // The person who deleted the company comes last, so the app keeps showing their company.
        $members = $members->sortBy(fn (User $member) => $by !== null && $member->is($by) ? 1 : 0);

        foreach ($members as $member) {
            $hadItOpen = $member->current_team_id === $teamId;
            $member->refresh();

            if (! $member->teams()->exists()) {
                $this->createTeam->handle($member, $member->name."'s Team", isPersonal: true);

                continue;
            }

            if (! $hadItOpen) {
                continue;
            }

            // The person who deleted it goes to their first other company, the others to their own company.
            $next = $by !== null && $member->is($by)
                ? $member->fallbackTeam()
                : ($member->personalTeam() ?? $member->fallbackTeam());

            if ($next !== null) {
                $member->switchTeam($next);
            }
        }
    }
}
