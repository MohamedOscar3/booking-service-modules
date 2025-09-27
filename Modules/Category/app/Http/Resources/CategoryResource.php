<?php

namespace Modules\Category\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Auth\Http\Resources\UserResource;

/**
 * Category API resource
 *
 * @mixin \Modules\Category\Models\Category
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_updated_by' => $this->whenLoaded('lastUpdatedByUser', fn () => new UserResource($this->lastUpdatedByUser)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
