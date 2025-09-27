<?php

namespace Modules\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @description User resource
 *
 * @param  Request  $request
 * @return array<string, mixed>
 */
class UserResource extends JsonResource
{
    /**
     * Add token to user resource
     *
     * @param string $token
     * @return self
     */
    /**
     * Token
     */
    public ?string $token = null;

    /**
     * Add token to user resource
     */
    public function addToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'timezone' => $this->timezone,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->token != null) {
            $data['token'] = $this->token;
        }

        return $data;
    }
}
