<?php

namespace Modules\Booking\Exports;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Booking\Services\AdminReportService;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ServiceBookingRatesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected Request $request,
        protected AdminReportService $reportService
    ) {}

    public function collection()
    {
        return $this->reportService->getServiceBookingRates($this->request);
    }

    public function headings(): array
    {
        return [
            'Service ID',
            'Service Name',
            'Provider Name',
            'Total Bookings',
            'Pending Bookings',
            'Confirmed Bookings',
            'Completed Bookings',
            'Cancelled Bookings',
            'Confirmation Rate (%)',
            'Cancellation Rate (%)',
            'Completion Rate (%)',
            'Pending Rate (%)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['service_id'],
            $row['service_name'],
            $row['provider_name'],
            $row['total_bookings'],
            $row['pending_bookings'],
            $row['confirmed_bookings'],
            $row['completed_bookings'],
            $row['cancelled_bookings'],
            $row['confirmation_rate'],
            $row['cancellation_rate'],
            $row['completion_rate'],
            $row['pending_rate'],
        ];
    }

    public function title(): string
    {
        return 'Service Booking Rates Report';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
