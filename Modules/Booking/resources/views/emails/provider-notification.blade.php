<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
        }
        .notification-type {
            padding: 10px 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        .notification-type.new {
            background-color: #d4edda;
            color: #155724;
        }
        .notification-type.cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .notification-type.updated {
            background-color: #fff3cd;
            color: #856404;
        }
        .booking-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #495057;
        }
        .detail-value {
            color: #6c757d;
        }
        .status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }
        .status.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status.confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        .status.cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status.completed {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .customer-info {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .actions {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .action-button {
            display: inline-block;
            padding: 12px 24px;
            margin: 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 14px;
        }
        .action-button.confirm {
            background-color: #28a745;
            color: white;
        }
        .action-button.cancel {
            background-color: #dc3545;
            color: white;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Booking Notification</h1>
        </div>

        <div class="notification-type {{ $notificationType }}">
            @if($notificationType === 'new')
                New Booking Request
            @elseif($notificationType === 'cancelled')
                Booking Cancelled
            @elseif($notificationType === 'updated')
                Booking Updated
            @else
                Booking Notification
            @endif
        </div>

        <div class="content">
            <p>Hello {{ $booking->service->provider->name }},</p>

            @if($notificationType === 'new')
                <p>You have received a new booking request for your service.</p>
            @elseif($notificationType === 'cancelled')
                <p>A booking for your service has been cancelled by the customer.</p>
            @elseif($notificationType === 'updated')
                <p>A booking for your service has been updated.</p>
            @endif

            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Booking ID:</span>
                    <span class="detail-value">#{{ $booking->id }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Service:</span>
                    <span class="detail-value">{{ $booking->service->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time:</span>
                    <span class="detail-value">{{ $booking->date->format('l, F j, Y \a\t g:i A') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value">{{ $booking->service->duration }} minutes</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Price:</span>
                    <span class="detail-value">${{ number_format($booking->service->price, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status {{ $booking->status->value }}">{{ $booking->status->label() }}</span>
                    </span>
                </div>
                @if($booking->customer_notes)
                <div class="detail-row">
                    <span class="detail-label">Customer Notes:</span>
                    <span class="detail-value">{{ $booking->customer_notes }}</span>
                </div>
                @endif
                @if($booking->provider_notes)
                <div class="detail-row">
                    <span class="detail-label">Provider Notes:</span>
                    <span class="detail-value">{{ $booking->provider_notes }}</span>
                </div>
                @endif
            </div>

            <div class="customer-info">
                <p><strong>Customer Information:</strong></p>
                <p><strong>Name:</strong> {{ $customer->name }}</p>
                <p><strong>Email:</strong> {{ $customer->email }}</p>
                @if($customer->phone)
                <p><strong>Phone:</strong> {{ $customer->phone }}</p>
                @endif
            </div>

            @if($notificationType === 'new' && $booking->status === 'pending')
            <div class="actions">
                <p><strong>Actions Required:</strong></p>
                <p>Please log in to your dashboard to confirm or decline this booking request.</p>
                <a href="{{ url('/dashboard/bookings') }}" class="action-button confirm">View Booking</a>
            </div>
            @endif

            @if($notificationType === 'cancelled')
            <p>The customer has cancelled this booking. No further action is required from you.</p>
            @endif
        </div>

        <div class="footer">
            <p>You can manage all your bookings from your provider dashboard.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
