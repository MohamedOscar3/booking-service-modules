<?php

namespace Modules\Booking\DTOs;

use Modules\Booking\Enums\BookingStatusEnum;

/**
 * @property string|null $date
 * @property int|null $user_id
 * @property string|null $service_name
 * @property BookingStatusEnum|null $status
 * @property float|null $price
 * @property string|null $service_description
 * @property int|null $service_id
 */
class UpdateBookingDto
{
    public function __construct(
        public ?string $date = null,
        public ?int $user_id = null,
        public ?string $service_name = null,
        public ?BookingStatusEnum $status = null,
        public ?float $price = null,
        public ?string $service_description = null,
        public ?int $service_id = null,
    ) {}

    public static function from(array $data): self
    {
        $status = null;
        if (isset($data['status'])) {
            $status = $data['status'] instanceof BookingStatusEnum
                ? $data['status']
                : BookingStatusEnum::from($data['status']);
        }

        return new self(
            $data['date'] ?? null,
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            $data['service_name'] ?? null,
            $status,
            isset($data['price']) ? (float) $data['price'] : null,
            $data['service_description'] ?? null,
            isset($data['service_id']) ? (int) $data['service_id'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'date' => $this->date,
            'user_id' => $this->user_id,
            'service_name' => $this->service_name,
            'status' => $this->status,
            'price' => $this->price,
            'service_description' => $this->service_description,
            'service_id' => $this->service_id,
        ], fn ($value) => $value !== null);
    }
}
