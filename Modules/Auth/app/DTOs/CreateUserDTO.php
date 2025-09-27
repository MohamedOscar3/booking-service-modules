<?php

namespace Modules\Auth\DTOs;

/**
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role
 * @property string $timezone
 */
class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $role,
        public string $timezone,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            $data['name'],
            $data['email'],
            $data['password'],
            $data['role'],
            $data['timezone'],
        );
    }
}
