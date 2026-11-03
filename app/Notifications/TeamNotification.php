<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Something that happened in a company workspace that a team member should know about.
 * Shown under the bell in the header of that workspace (never e-mailed).
 */
class TeamNotification extends Notification
{
    /**
     * @param  string  $kind  what happened, e.g. "ticket.assigned"
     * @param  string  $url  the page about it (a path within the app)
     */
    public function __construct(
        public int $teamId,
        public string $kind,
        public string $title,
        public ?string $body,
        public string $url,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The type stored with the notification.
     */
    public function databaseType(object $notifiable): string
    {
        return $this->kind;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{team_id: int, kind: string, title: string, body: string|null, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->teamId,
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
