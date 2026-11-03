<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
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
            'relationship' => $this->relationship->value,
            'relationship_label' => $this->relationship->label(),
            'relationship_color' => $this->relationship->color(),
            'business_type' => $this->business_type?->value,
            'business_type_label' => $this->business_type?->label(),
            'ntn' => $this->ntn,
            'strn' => $this->strn,
            'cnic' => $this->cnic,
            'sales_tax_registered' => $this->sales_tax_registered,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'industry' => $this->industry,
            'responsible_user_id' => $this->responsible_user_id,
            'payment_term_id' => $this->payment_term_id,
            'currency' => $this->currency,
            'language' => $this->language,
            'iban' => $this->iban,
            'remarks' => $this->remarks,
            'custom_fields' => (object) ($this->custom_fields ?? []),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'responsible_user' => $this->whenLoaded('responsibleUser', fn () => $this->responsibleUser?->only(['id', 'name'])),
            'payment_term' => $this->whenLoaded('paymentTerm', fn () => $this->paymentTerm?->only(['id', 'name'])),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'primary_address' => $this->whenLoaded('addresses', fn () => $this->addressFor()?->singleLine()),
            'contacts_count' => $this->whenCounted('contacts'),
            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->map(function (Contact $contact) {
                /** @var CompanyContact $link */
                $link = $contact->getRelation('pivot');

                return [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'mobile' => $contact->mobile,
                    'position' => $link->position,
                    'is_primary' => $link->is_primary,
                    'is_decision_maker' => $link->is_decision_maker,
                ];
            })),
        ];
    }
}
