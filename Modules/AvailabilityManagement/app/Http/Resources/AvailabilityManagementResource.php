<?php

namespace Modules\AvailabilityManagement\Http\Resources;

use App\Services\TimezoneService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\AvailabilityManagement\Enums\SlotType;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;

/** @mixin AvailabilityManagement */
class AvailabilityManagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timeZoneService = app(TimezoneService::class);

        $from = $timeZoneService->convertFromTimezone(Carbon::parse($this->from), auth()->user()->timezone);
        $to = $timeZoneService->convertFromTimezone(Carbon::parse($this->to), auth()->user()->timezone);

        $format = 'H:i';
        if ($this->type == SlotType::once) {
            $format = 'Y-m-d H:i';
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'week_day' => $this->week_day,
            'from' => $from->format($format),
            'to' => $to->format($format),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'provider_id' => $this->provider_id,
            'provider' => $this->whenLoaded('provider', function () {
                return [
                    'id' => $this->provider->id,
                    'name' => $this->provider->name,
                ];
            }),
        ];
    }
}
