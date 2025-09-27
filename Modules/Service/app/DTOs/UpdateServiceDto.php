<?php

namespace Modules\Service\DTOs;

/**
 * @property string|null $name
 * @property string|null $description
 * @property int|null $duration
 * @property float|null $price
 * @property int|null $provider_id
 * @property int|null $category_id
 * @property bool|null $status
 */
class UpdateServiceDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?int $duration = null,
        public ?float $price = null,
        public ?int $provider_id = null,
        public ?int $category_id = null,
        public ?bool $status = null,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            $data['name'] ?? null,
            $data['description'] ?? null,
            isset($data['duration']) ? (int) $data['duration'] : null,
            isset($data['price']) ? (float) $data['price'] : null,
            isset($data['provider_id']) ? (int) $data['provider_id'] : null,
            isset($data['category_id']) ? (int) $data['category_id'] : null,
            isset($data['status']) ? (bool) $data['status'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'duration' => $this->duration,
            'price' => $this->price,
            'provider_id' => $this->provider_id,
            'category_id' => $this->category_id,
            'status' => $this->status,
        ], fn ($value) => $value !== null);
    }
}
