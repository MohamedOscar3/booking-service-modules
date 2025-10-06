<?php

namespace Modules\Booking\Console\Commands;

use Illuminate\Console\Command;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Models\Booking;

class DeleteUnconfirmedCommand extends Command
{
    protected $signature = 'delete:unconfirmed';

    protected $description = 'Delete unconfirmed or expired bookings';

    public function handle(): void {
        $this->info('Deleting unconfirmed bookings...');

        Booking::whereIn('status', [BookingStatusEnum::PENDING])
            ->where('date', '<', now()->subHour())
            ->delete();

        $this->info('Unconfirmed bookings deleted successfully.');
    }
}
