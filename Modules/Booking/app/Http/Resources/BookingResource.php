<?php

namespace Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Booking\Models\Booking;
use Modules\Service\Http\Resources\ServiceResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'status' => $this->status,
            'price' => $this->price,
            'service_description' => $this->service_description,
            'customer_notes' => $this->customer_notes,
            'provider_notes' => $this->provider_notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user_id' => $this->user_id,
            'service_name' => $this->service_name,
            'service_id' => $this->service_id,

            'serviceName' => new ServiceResource($this->whenLoaded('serviceName')),
            'service' => new ServiceResource($this->whenLoaded('service')),
        ];
    }
}
