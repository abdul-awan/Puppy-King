<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
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
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'user' => $this->whenLoaded('user', fn () => $this->user?->only(['id', 'name'])),
            'can_delete' => $this->user_id === $request->user()?->id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
