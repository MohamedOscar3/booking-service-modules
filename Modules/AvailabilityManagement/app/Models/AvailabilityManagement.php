<?php

namespace Modules\AvailabilityManagement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\Database\Factories\AvailabilityManagementFactory;
use Modules\AvailabilityManagement\Enums\SlotType;

class AvailabilityManagement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider_id',
        'type',
        'week_day',
        'from',
        'to',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => SlotType::class,
            'week_day' => 'integer',
            'from' => 'datetime:Y-m-d H:i',
            'to' => 'datetime:Y-m-d H:i',
            'status' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    protected static function newFactory()
    {
        return AvailabilityManagementFactory::new();
    }
}
