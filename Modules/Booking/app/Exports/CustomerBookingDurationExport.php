<?php

namespace Modules\Booking\Exports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerBookingDurationExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $minBookings = $this->request->get('min_bookings', 1);

        $query = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->join('users as customers', 'bookings.user_id', '=', 'customers.id')
            ->select([
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.email as customer_email',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('AVG(services.duration) as average_duration_minutes'),
                DB::raw('SUM(services.duration) as total_duration_minutes'),
                DB::raw('AVG(bookings.price) as average_booking_value'),
                DB::raw('SUM(bookings.price) as total_spent'),
            ])
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        // Apply filters
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

        $results = $query->groupBy(['customers.id', 'customers.name', 'customers.email'])
            ->havingRaw('COUNT(*) >= ?', [$minBookings])
            ->orderBy('total_bookings', 'desc')
            ->get();

        // Enrich with additional data
        return $results->map(function ($customer) {
            // Get favorite service
            $favoriteServiceQuery = DB::table('bookings')
                ->join('services', 'bookings.service_id', '=', 'services.id')
                ->select(['services.name', DB::raw('COUNT(*) as booking_count')])
                ->where('bookings.user_id', $customer->customer_id)
                ->whereNull('bookings.deleted_at')
                ->whereIn('bookings.status', ['confirmed', 'completed']);

            $this->applyFiltersToSubQuery($favoriteServiceQuery);

            $favoriteService = $favoriteServiceQuery->groupBy('services.name')
                ->orderBy('booking_count', 'desc')
                ->first();

            // Get most frequent day and hour
            $frequencyQuery = DB::table('bookings')
                ->select([
                    DB::raw('DAYNAME(scheduled_at) as day_name'),
                    DB::raw('HOUR(scheduled_at) as hour'),
                    DB::raw('COUNT(*) as frequency'),
                ])
                ->where('bookings.user_id', $customer->customer_id)
                ->whereNull('bookings.deleted_at')
                ->whereIn('bookings.status', ['confirmed', 'completed']);

            if ($this->request->has('date_from')) {
                $frequencyQuery->where('bookings.scheduled_at', '>=', $this->request->date_from);
            }
            if ($this->request->has('date_to')) {
                $frequencyQuery->where('bookings.scheduled_at', '<=', $this->request->date_to.' 23:59:59');
            }

            $mostFrequentDay = (clone $frequencyQuery)
                ->groupBy('day_name')
                ->orderBy('frequency', 'desc')
                ->first();

            $mostFrequentHour = (clone $frequencyQuery)
                ->groupBy('hour')
                ->orderBy('frequency', 'desc')
                ->first();

            $customer->favorite_service = $favoriteService->name ?? 'N/A';
            $customer->most_frequent_day = $mostFrequentDay->day_name ?? 'N/A';
            $customer->most_frequent_hour = $mostFrequentHour ? (int) $mostFrequentHour->hour : null;

            return $customer;
        });
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
            $row->customer_id,
            $row->customer_name,
            $row->customer_email,
            (int) $row->total_bookings,
            (int) round($row->average_duration_minutes),
            (int) $row->total_duration_minutes,
            number_format(round((float) $row->average_booking_value, 2), 2),
            number_format((float) $row->total_spent, 2),
            $row->favorite_service,
            $row->most_frequent_day,
            $row->most_frequent_hour ? $row->most_frequent_hour.':00' : 'N/A',
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

    protected function applyFiltersToSubQuery($query): void
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
