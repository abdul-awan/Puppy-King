<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\CalendarEvent;
use App\Support\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('calendar:send-reminders')]
#[Description('Remind organizers and attendees of calendar events that are about to start (in the notification bell)')]
class SendEventRemindersCommand extends Command
{
    /**
     * Execute the console command.
     *
     * An event with a reminder of e.g. 15 minutes is reminded from 15 minutes before its start;
     * events that started more than an hour ago are skipped (the reminder would be too late).
     */
    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $sent = 0;

        $events = CalendarEvent::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('status', EventStatus::Planned)
            ->whereNotNull('reminder_minutes')
            ->whereNull('reminded_at')
            ->where('starts_at', '>', $now->subHour())
            ->where('starts_at', '<=', $now->addDays(2))
            ->whereHas('team', fn ($query) => $query->whereNull('suspended_at'))
            ->orderBy('starts_at')
            ->get();

        foreach ($events as $event) {
            if ($event->starts_at->subMinutes((int) $event->reminder_minutes)->greaterThan($now)) {
                continue;
            }

            try {
                Notifier::eventReminder($event);
                $event->forceFill(['reminded_at' => $now])->saveQuietly();
                $sent++;
            } catch (Throwable $e) {
                report($e);
                $this->components->error("Reminder for event {$event->id} failed: {$e->getMessage()}");
            }
        }

        $this->components->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
