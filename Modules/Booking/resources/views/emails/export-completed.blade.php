<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isSuccess ? 'Export Completed' : 'Export Failed' }}</title>
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
            background-color: {{ $isSuccess ? '#10b981' : '#ef4444' }};
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .download-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }
        .error-section {
            background-color: #fef2f2;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ef4444;
        }
        .btn {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 15px;
        }
        .btn:hover {
            background-color: #2563eb;
        }
        .footer {
            background-color: #374151;
            color: white;
            padding: 20px;
            border-radius: 0 0 8px 8px;
            text-align: center;
            font-size: 14px;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="icon">{{ $isSuccess ? '✅' : '❌' }}</div>
        <h1>{{ $isSuccess ? 'Export Completed Successfully' : 'Export Failed' }}</h1>
    </div>

    <div class="content">
        <p>Hello {{ $user->name }},</p>

        @if($isSuccess)
            <p>Your <strong>{{ $exportDisplayName }}</strong> has been successfully generated and is attached to this email.</p>

            <div class="download-section">
                <h3>📋 Export Details</h3>
                <ul>
                    <li><strong>Report Type:</strong> {{ $exportDisplayName }}</li>
                    <li><strong>File Name:</strong> {{ $filename }}</li>
                    <li><strong>Generated:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</li>
                </ul>

                <p><strong>📎 The Excel file is attached to this email.</strong></p>
            </div>

            <div style="background-color: #fef3c7; padding: 15px; border-radius: 6px; border-left: 4px solid #f59e0b;">
                <p><strong>💡 Tip:</strong></p>
                <p>Please download and save the attached file. The report contains all the data you requested based on your selected filters.</p>
            </div>
        @else
            <p>Unfortunately, your <strong>{{ $exportDisplayName }}</strong> could not be generated due to an error.</p>

            <div class="error-section">
                <h3>❌ Error Details</h3>
                <p><strong>Report Type:</strong> {{ $exportDisplayName }}</p>
                <p><strong>Time:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</p>
                @if($errorMessage)
                    <p><strong>Error Message:</strong> {{ $errorMessage }}</p>
                @endif
            </div>

            <p>Please contact the system administrator or try generating the report again. If the problem persists, please provide the error details above.</p>
        @endif

        <p>Thank you for using our reporting system.</p>

        <p>Best regards,<br>
        <strong>{{ config('app.name') }} Admin Team</strong></p>
    </div>

    <div class="footer">
        <p>This is an automated message from {{ config('app.name') }}. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>