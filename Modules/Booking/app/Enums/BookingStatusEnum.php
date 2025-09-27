<?php

namespace Modules\Booking\Enums;

enum BookingStatusEnum: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    /**
     * Get valid transitions from current status
     */
    public function validTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::COMPLETED, self::CANCELLED],
            self::CANCELLED => [], // Cannot transition from cancelled
            self::COMPLETED => [], // Cannot transition from completed
        };
    }

    /**
     * Check if transition to new status is valid
     */
    public function canTransitionTo(BookingStatusEnum $newStatus): bool
    {
        return in_array($newStatus, $this->validTransitions());
    }

    /**
     * Get status label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Confirmation',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * Get status color for UI
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::CONFIRMED => 'info',
            self::CANCELLED => 'danger',
            self::COMPLETED => 'success',
        };
    }

    /**
     * Check if status is final (cannot be changed)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::CANCELLED, self::COMPLETED]);
    }

    /**
     * Check if status allows booking modifications
     */
    public function allowsModification(): bool
    {
        return $this === self::PENDING;
    }
}
