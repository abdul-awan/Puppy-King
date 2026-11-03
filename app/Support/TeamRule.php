<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation rules that only accept records belonging to the current team.
 */
final class TeamRule
{
    /**
     * Require the value to exist in the table for the current team.
     */
    public static function exists(string $table, string $column = 'id', bool $softDeletes = false): Exists
    {
        $rule = Rule::exists($table, $column)->where('team_id', app(CurrentTeam::class)->id());

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }

    /**
     * Require the value to be the id of a member of the current team.
     */
    public static function member(): Exists
    {
        return Rule::exists('team_members', 'user_id')->where('team_id', app(CurrentTeam::class)->id());
    }
}
