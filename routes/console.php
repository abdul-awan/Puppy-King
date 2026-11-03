<?php

use App\Models\TeamInvitation;
use App\Support\CurrentTeam;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * The scheduled commands run inside the scheduler's own process (Schedule::call) rather than in a
 * process of their own (Schedule::command): shared hosting such as Hostinger switches off
 * proc_open, which starting another process needs. Every run starts and ends without a current
 * company, and a command that fails is reported like any other failing scheduled task. A crash
 * (e.g. running out of memory) ends the scheduler itself, so the locks against overlapping runs
 * expire after an hour (10 minutes for the reminders that run every minute) instead of a day.
 */
$inProcess = fn (string $command): Closure => function () use ($command): void {
    $currentTeam = app(CurrentTeam::class);
    $currentTeam->set(null);

    try {
        if (Artisan::call($command) !== 0) {
            throw new RuntimeException("{$command} failed: ".trim(Artisan::output()));
        }
    } finally {
        $currentTeam->set(null);
    }
};

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::call($inProcess('invoices:generate-recurring'))
    ->name('invoices:generate-recurring')
    ->dailyAt('06:00')
    ->withoutOverlapping(60);

Schedule::call($inProcess('invoices:send-reminders'))
    ->name('invoices:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping(60);

Schedule::call($inProcess('calendar:send-reminders'))
    ->name('calendar:send-reminders')
    ->everyMinute()
    ->withoutOverlapping(10);
