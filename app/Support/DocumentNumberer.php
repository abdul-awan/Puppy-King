<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Models\Department;
use App\Models\NumberSequence;
use App\Models\Team;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Hands out gapless, sequential document numbers such as "INV-2627-0001".
 *
 * Supported pattern tokens: {FY} (2026-27), {FYS} (2627), {YYYY}, {YY}, {MM} and {SEQ}.
 * The counter resets per fiscal year when {FY}/{FYS} is used, per month with {MM},
 * per calendar year with {YYYY}/{YY}, and never otherwise.
 */
class DocumentNumberer
{
    /**
     * Allocate the next number for the given document type.
     */
    public function next(Team $team, DocumentType $type, DateTimeInterface $date, ?Department $department = null): string
    {
        [$pattern, $padding, $fiscalYear, $keys] = $this->context($team, $type, $date, $department);

        $number = DB::transaction(function () use ($keys): int {
            NumberSequence::withoutGlobalScopes()->insertOrIgnore([
                ...$keys,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = NumberSequence::withoutGlobalScopes()
                ->where($keys)
                ->lockForUpdate()
                ->firstOrFail();

            $number = $sequence->next_number;

            $sequence->forceFill(['next_number' => $number + 1])->save();

            return $number;
        });

        return $this->format($pattern, $padding, $number, $date, $fiscalYear);
    }

    /**
     * Show the number the next document would get, without allocating it.
     */
    public function preview(Team $team, DocumentType $type, DateTimeInterface $date, ?Department $department = null): string
    {
        [$pattern, $padding, $fiscalYear, $keys] = $this->context($team, $type, $date, $department);

        $next = NumberSequence::withoutGlobalScopes()->where($keys)->value('next_number') ?? 1;

        return $this->format($pattern, $padding, (int) $next, $date, $fiscalYear);
    }

    /**
     * Set the next number for the current period of a document type.
     */
    public function setNext(Team $team, DocumentType $type, DateTimeInterface $date, int $next, ?Department $department = null): void
    {
        [, , , $keys] = $this->context($team, $type, $date, $department);

        NumberSequence::withoutGlobalScopes()->updateOrInsert($keys, [
            'next_number' => max(1, $next),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get the counter period key for a pattern.
     */
    public function period(string $pattern, DateTimeInterface $date, FiscalYear $fiscalYear): string
    {
        return match (true) {
            str_contains($pattern, '{FY}'), str_contains($pattern, '{FYS}') => 'FY'.$fiscalYear->label(),
            str_contains($pattern, '{MM}') => $date->format('Y-m'),
            str_contains($pattern, '{YYYY}'), str_contains($pattern, '{YY}') => $date->format('Y'),
            default => 'all',
        };
    }

    /**
     * Render a document number from a pattern.
     */
    public function format(string $pattern, int $padding, int $number, DateTimeInterface $date, FiscalYear $fiscalYear): string
    {
        return strtr($pattern, [
            '{FY}' => $fiscalYear->label(),
            '{FYS}' => $fiscalYear->short(),
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{SEQ}' => str_pad((string) $number, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Resolve the pattern, padding, fiscal year and sequence keys for a request.
     *
     * @return array{0: string, 1: int, 2: FiscalYear, 3: array{team_id: int, type: string, department_id: int, period: string}}
     */
    protected function context(Team $team, DocumentType $type, DateTimeInterface $date, ?Department $department): array
    {
        $settings = TeamSettings::for($team);
        ['pattern' => $pattern, 'padding' => $padding] = $settings->numbering($type);
        $fiscalYear = FiscalYear::for($date, $settings->fiscalYearStartMonth());

        return [$pattern, $padding, $fiscalYear, [
            'team_id' => $team->id,
            'type' => $type->value,
            'department_id' => $type->numberedPerDepartment() ? ($department->id ?? 0) : 0,
            'period' => $this->period($pattern, $date, $fiscalYear),
        ]];
    }
}
