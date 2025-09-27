<?php

namespace Modules\Booking\DTOs;

use Modules\Booking\Enums\BookingStatusEnum;

/**
 * @property string $date
 * @property string $time
 * @property int $user_id
 * @property string $service_name
 * @property BookingStatusEnum $status
 * @property float $price
 * @property string $service_description
 * @property int $service_id
 * @property int $provider_id
 * @property int|null $slot_id
 * @property string|null $customer_notes
 */
class CreateBookingDto
{
    public function __construct(
        public string $date,
        public string $time,
        public int $user_id,
        public string $service_name,
        public BookingStatusEnum $status,
        public float $price,
        public string $service_description,
        public int $service_id,
        public int $provider_id,
        public ?int $slot_id = null,
        public ?string $customer_notes = null,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            $data['date'],
            $data['time'],
            $data['user_id'],
            $data['service_name'],
            $data['status'] instanceof BookingStatusEnum ? $data['status'] : BookingStatusEnum::from($data['status']),
            (float) $data['price'],
            $data['service_description'],
            $data['service_id'],
            $data['provider_id'],
            $data['slot_id'] ?? null,
            $data['customer_notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'time' => $this->time,
            'user_id' => $this->user_id,
            'service_name' => $this->service_name,
            'status' => $this->status,
            'price' => $this->price,
            'service_description' => $this->service_description,
            'service_id' => $this->service_id,
            'provider_id' => $this->provider_id,
            'slot_id' => $this->slot_id,
            'customer_notes' => $this->customer_notes,
        ];
    }
}
