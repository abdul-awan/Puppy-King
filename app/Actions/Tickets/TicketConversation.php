<?php

namespace App\Actions\Tickets;

use App\Enums\EmailTemplateType;
use App\Enums\TicketStatus;
use App\Mail\DocumentMail;
use App\Models\EmailTemplate;
use App\Models\SentEmail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adds messages to a ticket and keeps its status up to date.
 */
class TicketConversation
{
    /**
     * A team member answers the customer (e-mailed when possible) or adds an internal note.
     */
    public function reply(Ticket $ticket, User $user, string $body, bool $internal = false, bool $email = true, ?TicketStatus $status = null): TicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $user, $body, $internal, $status) {
            $message = new TicketMessage(['user_id' => $user->id, 'body' => $body, 'is_internal' => $internal]);
            $message->team_id = $ticket->team_id;
            $message->ticket_id = $ticket->id;
            $message->save();

            if (! $internal) {
                $ticket->first_response_at ??= now();
            }

            // After an answer the ball is in the customer's court, unless another status was chosen.
            $ticket->status = $status ?? ($internal
                ? ($ticket->status === TicketStatus::New ? TicketStatus::Open : $ticket->status)
                : TicketStatus::Waiting);
            $ticket->assignee_id ??= $user->id;
            $ticket->save();

            return $message;
        });

        if (! $internal && $email && filled($ticket->customerEmail())) {
            $this->email($ticket, $message, $user);
        }

        return $message;
    }

    /**
     * The customer writes (through the link in the e-mails or the support form).
     */
    public function customerReply(Ticket $ticket, string $name, string $body): TicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $name, $body) {
            $message = new TicketMessage(['author_name' => $name, 'body' => $body]);
            $message->team_id = $ticket->team_id;
            $message->ticket_id = $ticket->id;
            $message->save();

            $ticket->last_customer_message_at = now();

            if (in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Closed], true)) {
                $ticket->status = TicketStatus::Open;
            }

            $ticket->save();

            return $message;
        });

        Notifier::ticketReplied($ticket, $message);

        return $message;
    }

    /**
     * E-mail a reply to the customer with the team's "ticket reply" template.
     */
    private function email(Ticket $ticket, TicketMessage $message, User $sender): void
    {
        $team = $ticket->team()->firstOrFail();
        $department = $team->defaultDepartment();
        $companyName = $department?->displayName() ?? $team->name;
        $to = (string) $ticket->customerEmail();

        $template = EmailTemplate::query()
            ->withoutGlobalScopes()
            ->where('team_id', $ticket->team_id)
            ->where('type', EmailTemplateType::TicketReply)
            ->orderByDesc('is_default')
            ->first();

        $rendered = $template?->render([
            '{ticket_number}' => $ticket->number,
            '{ticket_subject}' => $ticket->subject,
            '{message}' => $message->body,
            '{link}' => $ticket->publicUrl(),
            '{customer_name}' => $ticket->customerLabel(),
            '{contact_name}' => $ticket->contact->full_name ?? $ticket->customerLabel(),
            '{company_name}' => $companyName,
            '{user_name}' => $sender->name,
        ]) ?? [
            'subject' => "Re: [{$ticket->number}] {$ticket->subject}",
            'body' => $message->body."\n\n".$ticket->publicUrl(),
        ];

        $status = 'sent';
        $error = null;

        try {
            Mail::to($to)->send(new DocumentMail(
                subjectLine: $rendered['subject'],
                body: $rendered['body'],
                senderName: $companyName,
                replyToAddress: $department->email ?? $sender->email,
            ));
        } catch (Throwable $e) {
            report($e);
            $status = 'failed';
            $error = Str::limit($e->getMessage(), 500);
        }

        $sent = new SentEmail([
            'team_id' => $ticket->team_id,
            'user_id' => $sender->id,
            'to' => $to,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'attachments' => [],
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
        $sent->emailable()->associate($ticket);
        $sent->save();

        if ($status === 'sent') {
            $message->forceFill(['emailed_at' => now()])->save();
        }
    }
}
