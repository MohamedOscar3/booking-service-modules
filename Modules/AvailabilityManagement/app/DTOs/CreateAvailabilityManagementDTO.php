<?php

namespace Modules\AvailabilityManagement\DTOs;

use Modules\AvailabilityManagement\Enums\SlotType;

/**
 * @property int $provider_id
 * @property SlotType $type
 * @property int $week_day
 * @property string $from
 * @property string $to
 * @property string $date
 * @property bool $status
 */
class CreateAvailabilityManagementDTO
{
    public function __construct(
        public int $provider_id,
        public SlotType $type,
        public ?int $week_day,
        public string $from,
        public string $to,
        public bool $status = true,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            $data['provider_id'],
            SlotType::from($data['type']),
            $data['week_day'] ?? null,
            $data['from'],
            $data['to'],
            $data['status'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'provider_id' => $this->provider_id,
            'type' => $this->type->value,
            'week_day' => $this->week_day,
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->status,
        ];
    }
}
