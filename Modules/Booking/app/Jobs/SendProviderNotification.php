<?php

namespace Modules\Booking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Modules\Booking\Mail\ProviderBookingNotificationMail;
use Modules\Booking\Models\Booking;

class SendProviderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $notificationType
    ) {}

    public function handle(): void
    {
        // Load necessary relationships
        $this->booking->load(['user', 'service', 'provider']);

        // Get provider from service relationship
        $provider = $this->booking->service->provider;

        if ($provider && $provider->email) {
            // Send notification email to provider
            Mail::to($provider->email)
                ->send(new ProviderBookingNotificationMail($this->booking, $this->notificationType));
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'booking:'.$this->booking->id,
            'email:provider-notification',
            'provider:'.$this->booking->service->provider_id,
            'type:'.$this->notificationType,
        ];
    }
}
