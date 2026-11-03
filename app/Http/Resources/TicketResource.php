<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'priority_color' => $this->priority->color(),
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'project_id' => $this->project_id,
            'assignee_id' => $this->assignee_id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_label' => $this->customerLabel(),
            'reply_to' => $this->customerEmail(),
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? ['id' => $this->contact->id, 'name' => $this->contact->full_name] : null),
            'project' => $this->whenLoaded('project', fn () => $this->project?->only(['id', 'title'])),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee?->only(['id', 'name'])),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn (TicketMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'is_internal' => $message->is_internal,
                'from_customer' => $message->fromCustomer(),
                'author' => $message->user->name ?? $message->author_name ?? 'Customer',
                'emailed_at' => $message->emailed_at?->toIso8601String(),
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values()),
            'messages_count' => $this->whenCounted('messages'),
            'public_url' => $this->publicUrl(),
            'first_response_at' => $this->first_response_at?->toIso8601String(),
            'last_customer_message_at' => $this->last_customer_message_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
