<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Status Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background-color: #ffc107; }
        .status-confirmed { background-color: #17a2b8; }
        .status-completed { background-color: #28a745; }
        .status-cancelled { background-color: #dc3545; }
        .booking-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .detail-row {
            margin: 8px 0;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Booking Status Update</h1>
        <p>Your booking status has been updated</p>
    </div>

    <p>Hello {{ $customer->name }},</p>

    <p>We wanted to notify you that your booking status has been updated:</p>

    <div class="booking-details">
        <div class="detail-row">
            <span class="label">Service:</span> {{ $service->name }}
        </div>
        <div class="detail-row">
            <span class="label">Provider:</span> {{ $provider->name }}
        </div>
        <div class="detail-row">
            <span class="label">Date & Time:</span> {{ $booking->date->format('M d, Y \a\t g:i A') }}
        </div>
        <div class="detail-row">
            <span class="label">Previous Status:</span>
            <span class="status-badge status-{{ $oldStatus->value }}">{{ $oldStatus->label() }}</span>
        </div>
        <div class="detail-row">
            <span class="label">New Status:</span>
            <span class="status-badge status-{{ $newStatus->value }}">{{ $newStatus->label() }}</span>
        </div>
        @if($booking->price)
        <div class="detail-row">
            <span class="label">Price:</span> ${{ number_format($booking->price, 2) }}
        </div>
        @endif
        @if($booking->provider_notes)
        <div class="detail-row">
            <span class="label">Provider Notes:</span> {{ $booking->provider_notes }}
        </div>
        @endif
    </div>

    @if($newStatus === \Modules\Booking\Enums\BookingStatusEnum::CONFIRMED)
        <p><strong>Great news!</strong> Your booking has been confirmed. Please make sure to arrive on time for your appointment.</p>
    @elseif($newStatus === \Modules\Booking\Enums\BookingStatusEnum::COMPLETED)
        <p><strong>Thank you!</strong> Your service has been completed. We hope you had a great experience!</p>
    @elseif($newStatus === \Modules\Booking\Enums\BookingStatusEnum::CANCELLED)
        <p>Your booking has been cancelled. If you have any questions, please contact the provider directly.</p>
    @endif

    <p>If you have any questions or concerns, please don't hesitate to contact us.</p>

    <p>Best regards,<br>
    {{ config('app.name') }} Team</p>
</body>
</html>