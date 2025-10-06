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

class CustomerBookingDurationExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected Request $request,
        protected AdminReportService $reportService
    ) {}

    public function collection()
    {
        return $this->reportService->getCustomerBookingDuration($this->request);
    }

    public function headings(): array
    {
        return [
            'Customer ID',
            'Customer Name',
            'Customer Email',
            'Total Bookings',
            'Average Duration (minutes)',
            'Total Duration (minutes)',
            'Average Booking Value',
            'Total Spent',
            'Favorite Service',
            'Most Frequent Day',
            'Most Frequent Hour',
        ];
    }

    public function map($row): array
    {
        return [
            $row['customer_id'],
            $row['customer_name'],
            $row['customer_email'],
            $row['total_bookings'],
            $row['average_duration_minutes'],
            $row['total_duration_minutes'],
            $row['average_booking_value'],
            number_format($row['total_spent'], 2),
            $row['favorite_service'],
            $row['most_frequent_day'],
            $row['most_frequent_hour'] ? $row['most_frequent_hour'].':00' : 'N/A',
        ];
    }

    public function title(): string
    {
        return 'Customer Booking Duration Report';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
