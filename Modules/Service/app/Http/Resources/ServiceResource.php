<?php

namespace Modules\Service\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Category\Http\Resources\CategoryResource;
use Modules\Service\Models\Service;

/** @mixin Service */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'duration' => $this->duration,
            'price' => $this->price,
            'status' => $this->status,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'provider_id' => $this->provider_id,
            'category_id' => $this->category_id,

            'category' => new CategoryResource($this->whenLoaded('category')),
            'provider' => $this->whenLoaded('provider', function () {
                return [
                    'id' => $this->provider->id,
                    'name' => $this->provider->name,
                    'email' => $this->provider->email,
                ];
            }),
        ];
        if ($request->has('slots')) {
            $data['slots'] = $request->slots;
        }

        return $data;
    }

    public function with(Request $request) {}
}
