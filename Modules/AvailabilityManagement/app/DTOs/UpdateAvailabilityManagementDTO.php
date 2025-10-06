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
            array_key_exists('week_day', $data) ? (isset($data['week_day']) ? (int) $data['week_day'] : null) : null,
            $data['from'] ?? null,
            $data['to'] ?? null,
            isset($data['status']) ? (bool) $data['status'] : null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->provider_id !== null) {
            $data['provider_id'] = $this->provider_id;
        }

        if ($this->type !== null) {
            $data['type'] = $this->type->value;
        }

        if ($this->type === SlotType::once) {
            $data['week_day'] = null;
        } elseif ($this->week_day !== null) {
            $data['week_day'] = $this->week_day;
        }

        if ($this->from !== null) {
            $data['from'] = $this->from;
        }

        if ($this->to !== null) {
            $data['to'] = $this->to;
        }

        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        return $data;
    }
}
