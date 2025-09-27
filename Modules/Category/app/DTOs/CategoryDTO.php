<?php

namespace Modules\Category\DTOs;

/**
 * CategoryDTO
 *
 * @description Data Transfer Object for category data
 */
class CategoryDTO
{
    public function __construct(
        public readonly string $name
    ) {}

    /**
     * Create DTO from request data
     */
    public static function fromRequest(array $data): static
    {
        return new static(
            name: $data['name']
        );
    }

    /**
     * Convert DTO to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
