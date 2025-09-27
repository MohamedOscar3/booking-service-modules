<?php

namespace Modules\Booking\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;
use Modules\Booking\Database\Factories\BookingFactory;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Service\Models\Service;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'date',
        'time',
        'user_id',
        'service_name',
        'status',
        'price',
        'service_description',
        'service_id',
        'slot_id',
        'provider_id',
        'provider_notes',
        'customer_notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AvailabilityManagement::class, 'slot_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'status' => BookingStatusEnum::class,
        ];
    }

    protected static function newFactory(): BookingFactory
    {
        return BookingFactory::new();
    }

    /**
     * Get the booking end time based on service duration
     */
    public function getEndTimeAttribute(): Carbon
    {
        return $this->date->copy()->addMinutes($this->service->duration);
    }

    /**
     * Check if booking is in the past
     */
    public function isPast(): bool
    {
        return $this->date->isPast();
    }

    /**
     * Check if booking can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return ! $this->isPast() &&
               ! in_array($this->status, [BookingStatusEnum::CANCELLED, BookingStatusEnum::COMPLETED]);
    }

    /**
     * Check if booking can be confirmed
     */
    public function canBeConfirmed(): bool
    {
        return ! $this->isPast() &&
               $this->status === BookingStatusEnum::PENDING;
    }

    /**
     * Check if booking can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === BookingStatusEnum::CONFIRMED;
    }

    /**
     * Check if booking overlaps with given time range
     */
    public function overlaps(Carbon $startTime, Carbon $endTime): bool
    {
        $bookingStart = $this->date;
        $bookingEnd = $this->end_time;

        return $startTime->lt($bookingEnd) && $endTime->gt($bookingStart);
    }
}
