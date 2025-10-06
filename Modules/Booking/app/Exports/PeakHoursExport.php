<?php

namespace Modules\Booking\Exports;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Booking\Services\AdminReportService;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PeakHoursExport implements WithMultipleSheets
{
    public function __construct(
        protected Request $request,
        protected AdminReportService $reportService
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        $groupBy = $this->request->get('groupby', 'both');

        if (in_array($groupBy, ['hour', 'both'])) {
            $sheets[] = new PeakHoursByHourSheet($this->request, $this->reportService);
        }

        if (in_array($groupBy, ['day', 'both'])) {
            $sheets[] = new PeakHoursByDaySheet($this->request, $this->reportService);
        }

        if ($groupBy === 'both') {
            $sheets[] = new PeakHoursByDayAndHourSheet($this->request, $this->reportService);
        }

        return $sheets;
    }
}

class PeakHoursByHourSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected Request $request,
        protected AdminReportService $reportService
    ) {}

    public function collection()
    {
        $data = $this->reportService->getPeakHoursAnalysis($this->request);

        return collect($data['by_hour'] ?? []);
    }

    public function headings(): array
    {
        return ['Hour', 'Total Bookings', 'Percentage (%)'];
    }

    public function map($row): array
    {
        return [
            $row['hour'].':00',
            $row['total_bookings'],
            $row['percentage'],
        ];
    }

    public function title(): string
    {
        return 'Peak Hours by Hour';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

class PeakHoursByDaySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected Request $request,
        protected AdminReportService $reportService
    ) {}

    public function collection()
    {
        $data = $this->reportService->getPeakHoursAnalysis($this->request);

        return collect($data['by_day'] ?? []);
    }

    public function headings(): array
    {
        return ['Day Name', 'Day Number', 'Total Bookings', 'Percentage (%)'];
    }

    public function map($row): array
    {
        return [
            $row['day_name'],
            $row['day_number'],
            $row['total_bookings'],
            $row['percentage'],
        ];
    }

    public function title(): string
    {
        return 'Peak Hours by Day';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

class PeakHoursByDayAndHourSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected Request $request,
        protected AdminReportService $reportService
    ) {}

    public function collection()
    {
        $data = $this->reportService->getPeakHoursAnalysis($this->request);

        return collect($data['by_day_and_hour'] ?? []);
    }

    public function headings(): array
    {
        return ['Day Name', 'Day Number', 'Hour', 'Total Bookings', 'Percentage (%)'];
    }

    public function map($row): array
    {
        return [
            $row['day_name'],
            $row['day_number'],
            $row['hour'].':00',
            $row['total_bookings'],
            $row['percentage'],
        ];
    }

    public function title(): string
    {
        return 'Peak Hours by Day and Hour';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
