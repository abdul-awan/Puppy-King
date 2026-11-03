<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
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
            'salutation' => $this->salutation,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'cnic' => $this->cnic,
            'birthdate' => $this->birthdate?->toDateString(),
            'language' => $this->language,
            'responsible_user_id' => $this->responsible_user_id,
            'marketing_consent' => $this->marketing_consent,
            'remarks' => $this->remarks,
            'custom_fields' => (object) ($this->custom_fields ?? []),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'responsible_user' => $this->whenLoaded('responsibleUser', fn () => $this->responsibleUser?->only(['id', 'name'])),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'companies' => $this->whenLoaded('companies', fn () => $this->companies->map(function (Company $company) {
                /** @var CompanyContact $link */
                $link = $company->getRelation('pivot');

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'position' => $link->position,
                    'is_primary' => $link->is_primary,
                    'is_decision_maker' => $link->is_decision_maker,
                ];
            })),
        ];
    }
}
