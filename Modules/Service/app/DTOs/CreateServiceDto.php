<?php

namespace Modules\Service\DTOs;

/**
 * @property string $name
 * @property string $description
 * @property int $duration
 * @property float $price
 * @property int $provider_id
 * @property int $category_id
 * @property bool $status
 */
class CreateServiceDto
{
    public function __construct(
        public string $name,
        public string $description,
        public int $duration,
        public float $price,
        public int $provider_id,
        public int $category_id,
        public bool $status = true,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            $data['name'],
            $data['description'],
            $data['duration'],
            (float) $data['price'],
            $data['provider_id'],
            $data['category_id'],
            $data['status'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'duration' => $this->duration,
            'price' => $this->price,
            'provider_id' => $this->provider_id,
            'category_id' => $this->category_id,
            'status' => $this->status,
        ];
    }
}
