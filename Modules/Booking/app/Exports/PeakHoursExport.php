<?php

namespace Modules\Booking\Exports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PeakHoursExport implements WithMultipleSheets
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function sheets(): array
    {
        $sheets = [];
        $groupBy = $this->request->get('groupby', 'both');

        if (in_array($groupBy, ['hour', 'both'])) {
            $sheets[] = new PeakHoursByHourSheet($this->request);
        }

        if (in_array($groupBy, ['day', 'both'])) {
            $sheets[] = new PeakHoursByDaySheet($this->request);
        }

        if ($groupBy === 'both') {
            $sheets[] = new PeakHoursByDayAndHourSheet($this->request);
        }

        return $sheets;
    }
}

class PeakHoursByHourSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $baseQuery = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        $this->applyFilters($baseQuery);

        $totalBookings = $baseQuery->count();

        $hourlyData = clone $baseQuery;

        return $hourlyData
            ->select([
                DB::raw('HOUR(scheduled_at) as hour'),
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw($totalBookings > 0 ? 'ROUND((COUNT(*) / '.$totalBookings.') * 100, 1)' : '0 as percentage'),
            ])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    public function headings(): array
    {
        return ['Hour', 'Total Bookings', 'Percentage (%)'];
    }

    public function map($row): array
    {
        return [
            (int) $row->hour.':00',
            (int) $row->total_bookings,
            (float) $row->percentage,
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

    protected function applyFilters($query): void
    {
        if ($this->request->has('provider_id')) {
            $query->where('services.provider_id', $this->request->provider_id);
        }

        if ($this->request->has('service_id')) {
            $query->where('services.id', $this->request->service_id);
        }

        if ($this->request->has('date_from')) {
            $query->where('bookings.scheduled_at', '>=', $this->request->date_from);
        }

        if ($this->request->has('date_to')) {
            $query->where('bookings.scheduled_at', '<=', $this->request->date_to.' 23:59:59');
        }
    }
}

class PeakHoursByDaySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $baseQuery = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        $this->applyFilters($baseQuery);

        $totalBookings = $baseQuery->count();

        $dailyData = clone $baseQuery;

        return $dailyData
            ->select([
                DB::raw('DAYOFWEEK(scheduled_at) as day_number'),
                DB::raw('DAYNAME(scheduled_at) as day_name'),
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw($totalBookings > 0 ? 'ROUND((COUNT(*) / '.$totalBookings.') * 100, 1)' : '0 as percentage'),
            ])
            ->groupBy(['day_number', 'day_name'])
            ->orderBy('day_number')
            ->get();
    }

    public function headings(): array
    {
        return ['Day Name', 'Day Number', 'Total Bookings', 'Percentage (%)'];
    }

    public function map($row): array
    {
        return [
            $row->day_name,
            (int) $row->day_number,
            (int) $row->total_bookings,
            (float) $row->percentage,
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

    protected function applyFilters($query): void
    {
        if ($this->request->has('provider_id')) {
            $query->where('services.provider_id', $this->request->provider_id);
        }

        if ($this->request->has('service_id')) {
            $query->where('services.id', $this->request->service_id);
        }

        if ($this->request->has('date_from')) {
            $query->where('bookings.scheduled_at', '>=', $this->request->date_from);
        }

        if ($this->request->has('date_to')) {
            $query->where('bookings.scheduled_at', '<=', $this->request->date_to.' 23:59:59');
        }
    }
}

class PeakHoursByDayAndHourSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $baseQuery = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        $this->applyFilters($baseQuery);

        $totalBookings = $baseQuery->count();

        $combinedData = clone $baseQuery;

        return $combinedData
            ->select([
                DB::raw('DAYOFWEEK(scheduled_at) as day_number'),
                DB::raw('DAYNAME(scheduled_at) as day_name'),
                DB::raw('HOUR(scheduled_at) as hour'),
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw($totalBookings > 0 ? 'ROUND((COUNT(*) / '.$totalBookings.') * 100, 1)' : '0 as percentage'),
            ])
            ->groupBy(['day_number', 'day_name', 'hour'])
            ->orderBy('day_number')
            ->orderBy('hour')
            ->get();
    }

    public function headings(): array
    {
        return ['Day Name', 'Day Number', 'Hour', 'Total Bookings', 'Percentage (%)'];
    }

    public function map($row): array
    {
        return [
            $row->day_name,
            (int) $row->day_number,
            (int) $row->hour.':00',
            (int) $row->total_bookings,
            (float) $row->percentage,
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

    protected function applyFilters($query): void
    {
        if ($this->request->has('provider_id')) {
            $query->where('services.provider_id', $this->request->provider_id);
        }

        if ($this->request->has('service_id')) {
            $query->where('services.id', $this->request->service_id);
        }

        if ($this->request->has('date_from')) {
            $query->where('bookings.scheduled_at', '>=', $this->request->date_from);
        }

        if ($this->request->has('date_to')) {
            $query->where('bookings.scheduled_at', '<=', $this->request->date_to.' 23:59:59');
        }
    }
}
