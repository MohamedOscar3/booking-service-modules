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

class ProviderBookingsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->join('users as providers', 'services.provider_id', '=', 'providers.id')
            ->select([
                'providers.id as provider_id',
                'providers.name as provider_name',
                'providers.email as provider_email',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('SUM(bookings.price) as total_revenue'),
                DB::raw("SUM(CASE WHEN bookings.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings"),
                DB::raw('AVG(bookings.price) as average_booking_value'),
                DB::raw('COUNT(DISTINCT services.id) as services_offered'),
            ])
            ->whereNull('bookings.deleted_at');

        // Apply filters
        if ($this->request->has('provider_id')) {
            $query->where('providers.id', $this->request->provider_id);
        }

        if ($this->request->has('date_from')) {
            $query->where('bookings.scheduled_at', '>=', $this->request->date_from);
        }

        if ($this->request->has('date_to')) {
            $query->where('bookings.scheduled_at', '<=', $this->request->date_to.' 23:59:59');
        }

        if ($this->request->has('service_id')) {
            $query->where('services.id', $this->request->service_id);
        }

        return $query->groupBy(['providers.id', 'providers.name', 'providers.email'])
            ->orderBy('total_bookings', 'desc')
            ->get();
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
            $row->provider_id,
            $row->provider_name,
            $row->provider_email,
            (int) $row->total_bookings,
            number_format((float) $row->total_revenue, 2),
            (int) $row->pending_bookings,
            (int) $row->confirmed_bookings,
            (int) $row->completed_bookings,
            (int) $row->cancelled_bookings,
            number_format(round((float) $row->average_booking_value, 2), 2),
            (int) $row->services_offered,
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
