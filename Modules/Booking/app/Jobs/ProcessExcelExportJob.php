<?php

namespace Modules\Booking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Auth\Models\User;
use Modules\Booking\Exports\CustomerBookingDurationExport;
use Modules\Booking\Exports\PeakHoursExport;
use Modules\Booking\Exports\ProviderBookingsExport;
use Modules\Booking\Exports\ServiceBookingRatesExport;
use Modules\Booking\Mail\ExportCompletedMail;

class ProcessExcelExportJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected User $user;

    protected string $exportType;

    protected array $requestData;

    protected string $filename;

    public function __construct(User $user, string $exportType, array $requestData)
    {
        $this->user = $user;
        $this->exportType = $exportType;
        $this->requestData = $requestData;
        $this->filename = $this->generateFilename();
    }

    public function handle(): void
    {
        try {
            // Create a request object from the array data
            $request = new Request($this->requestData);

            // Get the appropriate export class
            $exportClass = $this->getExportClass($request);

            // Store the file in a protected directory
            $filePath = 'exports/admin/'.$this->filename;

            // Generate and store the Excel file
            Excel::store($exportClass, $filePath, 'local');

            // Send email notification with attached file
            Mail::to($this->user->email)->send(
                new ExportCompletedMail($this->user, $this->exportType, $this->filename, $filePath)
            );

        } catch (\Exception $e) {
            // Log the error and optionally send failure notification
            \Log::error('Excel export failed', [
                'user_id' => $this->user->id,
                'export_type' => $this->exportType,
                'error' => $e->getMessage(),
            ]);

            // Optionally send failure email notification
            Mail::to($this->user->email)->send(
                new ExportCompletedMail($this->user, $this->exportType, null, $e->getMessage())
            );
        }
    }

    protected function getExportClass(Request $request)
    {
        $reportService = app(\Modules\Booking\Services\AdminReportService::class);

        return match ($this->exportType) {
            'provider-bookings' => new ProviderBookingsExport($request, $reportService),
            'service-booking-rates' => new ServiceBookingRatesExport($request, $reportService),
            'peak-hours' => new PeakHoursExport($request, $reportService),
            'customer-booking-duration' => new CustomerBookingDurationExport($request, $reportService),
            default => throw new \InvalidArgumentException('Invalid export type: '.$this->exportType),
        };
    }

    protected function generateFilename(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $exportName = str_replace('-', '_', $this->exportType);

        return "admin_report_{$exportName}_{$timestamp}.xlsx";
    }
}
