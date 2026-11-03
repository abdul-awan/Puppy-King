<?php

use App\Models\Team;
use App\Support\CurrentTeam;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/**
 * The scheduled task with the given name (routes/console.php).
 */
function scheduledTask(string $name): CallbackEvent
{
    $task = collect(app(Schedule::class)->events())->first(fn (Event $event) => $event->description === $name);

    expect($task)->toBeInstanceOf(CallbackEvent::class);

    return $task;
}

test('every scheduled task runs inside the scheduler process, so hosting without proc_open can run them', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->reject(fn (Event $event) => $event instanceof CallbackEvent))->toBeEmpty()
        ->and($events->pluck('expression', 'description')->all())->toMatchArray([
            'invoices:generate-recurring' => '0 6 * * *',
            'invoices:send-reminders' => '0 9 * * *',
            'calendar:send-reminders' => '* * * * *',
        ]);

    $this->artisan('schedule:list')->assertSuccessful();
});

test('a scheduled command leaves no current company behind', function () {
    app(CurrentTeam::class)->set(Team::factory()->create());

    $task = scheduledTask('calendar:send-reminders');
    $task->run(app());

    expect($task->exitCode)->toBe(0)
        ->and(app(CurrentTeam::class)->get())->toBeNull();
});

test('a scheduled command starts without a current company and a failure is reported', function () {
    Artisan::shouldReceive('call')->once()->with('invoices:send-reminders')->andReturnUsing(function () {
        expect(app(CurrentTeam::class)->get())->toBeNull();

        return 1;
    });
    Artisan::shouldReceive('output')->once()->andReturn("Something went wrong\n");

    app(CurrentTeam::class)->set(Team::factory()->create());

    $task = scheduledTask('invoices:send-reminders');

    expect(fn () => $task->run(app()))->toThrow(RuntimeException::class, 'invoices:send-reminders failed: Something went wrong')
        ->and($task->exitCode)->toBe(1)
        ->and(app(CurrentTeam::class)->get())->toBeNull();
});
