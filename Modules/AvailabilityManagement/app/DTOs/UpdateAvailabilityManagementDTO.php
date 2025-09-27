<?php

namespace Modules\AvailabilityManagement\DTOs;

use Modules\AvailabilityManagement\Enums\SlotType;

/**
 * @property int|null $provider_id
 * @property SlotType|null $type
 * @property int|null $week_day
 * @property string|null $from
 * @property string|null $to
 * @property string|null $date
 * @property bool|null $status
 */
class UpdateAvailabilityManagementDTO
{
    public function __construct(
        public ?int $provider_id = null,
        public ?SlotType $type = null,
        public ?int $week_day = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?bool $status = null,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            isset($data['provider_id']) ? (int) $data['provider_id'] : null,
            isset($data['type']) ? SlotType::from($data['type']) : null,
            isset($data['week_day']) ? (int) $data['week_day'] : null,
            $data['from'] ?? null,
            $data['to'] ?? null,
            isset($data['status']) ? (bool) $data['status'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'provider_id' => $this->provider_id,
            'type' => $this->type?->value,
            'week_day' => $this->week_day,
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->status,
        ], fn ($value) => $value !== null);
    }
}
