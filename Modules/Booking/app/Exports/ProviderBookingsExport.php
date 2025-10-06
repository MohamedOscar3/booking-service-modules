<?php

namespace Modules\Booking\Exports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Booking\Services\AdminReportService;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProviderBookingsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        protected Request $request,
        protected AdminReportService $reportService
    ) {}

    public function collection()
    {
        $data = $this->reportService->getProviderBookingStats($this->request);
        Log::info('Provider Bookings Export Data', ['data'=>$data]);
        return $data;
    }

    public function headings(): array
    {
        return [
            'Provider ID',
            'Provider Name',
            'Provider Email',
            'Total Bookings',
            'Total Revenue',
            'Pending Bookings',
            'Confirmed Bookings',
            'Completed Bookings',
            'Cancelled Bookings',
            'Average Booking Value',
            'Services Offered',
        ];
    }

    public function map($row): array
    {
        return [
            $row['provider_id'],
            $row['provider_name'],
            $row['provider_email'],
            $row['total_bookings'],
            $row['total_revenue'],
            $row['pending_bookings'],
            $row['confirmed_bookings'],
            $row['completed_bookings'],
            $row['cancelled_bookings'],
            $row['average_booking_value'],
            $row['services_offered'],
        ];
    }

    public function title(): string
    {
        return 'Provider Bookings Report';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
