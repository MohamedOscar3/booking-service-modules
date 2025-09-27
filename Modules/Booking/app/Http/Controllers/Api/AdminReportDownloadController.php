<?php

namespace Modules\Booking\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Enums\Roles;
use Modules\Booking\Models\Booking;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Admin Report Downloads
 *
 * Download endpoints for admin-generated Excel reports.
 * Provides secure download links for queued export files.
 */
class AdminReportDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ApiResponseService $apiResponse
    ) {}

    /**
     * Download admin report file
     *
     * Download a previously generated Excel report file using the secure filename.
     * Files are stored in a protected directory and automatically cleaned up after 7 days.
     *
     * @authenticated
     *
     * @urlParam filename string required The secure filename provided in the export email. Example: admin_report_provider_bookings_2024-12-27_14-30-15.xlsx
     *
     * @response 200 binary/application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Report file not found or has expired"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function download(Request $request, string $filename): BinaryFileResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            abort(403, 'Admin access required to download reports');
        }

        // Validate filename format for security
        if (! preg_match('/^admin_report_[a-z_]+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.xlsx$/', $filename)) {
            abort(404, 'Invalid report filename format');
        }

        $filePath = 'exports/admin/'.$filename;

        // Check if file exists
        if (! Storage::disk('local')->exists($filePath)) {
            abort(404, 'Report file not found or has expired');
        }

        // Get the full path for download
        $fullPath = Storage::disk('local')->path($filePath);

        // Generate a user-friendly download name
        $downloadName = $this->generateDownloadName($filename);

        return response()->download($fullPath, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Generate a user-friendly download filename
     */
    protected function generateDownloadName(string $filename): string
    {
        // Extract the report type and timestamp from the filename
        if (preg_match('/^admin_report_([a-z_]+)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.xlsx$/', $filename, $matches)) {
            $reportType = $matches[1];
            $timestamp = $matches[2];

            $reportNames = [
                'provider_bookings' => 'Provider_Bookings_Report',
                'service_booking_rates' => 'Service_Booking_Rates_Report',
                'peak_hours' => 'Peak_Hours_Analysis_Report',
                'customer_booking_duration' => 'Customer_Booking_Duration_Report',
            ];

            $reportName = $reportNames[$reportType] ?? 'Admin_Report';

            return $reportName.'_'.$timestamp.'.xlsx';
        }

        return $filename;
    }
}
