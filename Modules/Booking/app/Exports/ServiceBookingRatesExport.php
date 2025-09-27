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

class ServiceBookingRatesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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
                'services.id as service_id',
                'services.name as service_name',
                'providers.name as provider_name',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw("SUM(CASE WHEN bookings.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings"),
            ])
            ->whereNull('bookings.deleted_at');

        // Apply filters
        if ($this->request->has('service_id')) {
            $query->where('services.id', $this->request->service_id);
        }

        if ($this->request->has('provider_id')) {
            $query->where('providers.id', $this->request->provider_id);
        }

        if ($this->request->has('date_from')) {
            $query->where('bookings.scheduled_at', '>=', $this->request->date_from);
        }

        if ($this->request->has('date_to')) {
            $query->where('bookings.scheduled_at', '<=', $this->request->date_to.' 23:59:59');
        }

        return $query->groupBy(['services.id', 'services.name', 'providers.name'])
            ->orderBy('total_bookings', 'desc')
            ->get();
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
        $total = $row->total_bookings;

        return [
            $row->service_id,
            $row->service_name,
            $row->provider_name,
            (int) $total,
            (int) $row->pending_bookings,
            (int) $row->confirmed_bookings,
            (int) $row->completed_bookings,
            (int) $row->cancelled_bookings,
            $total > 0 ? round(($row->confirmed_bookings / $total) * 100, 2) : 0,
            $total > 0 ? round(($row->cancelled_bookings / $total) * 100, 2) : 0,
            $total > 0 ? round(($row->completed_bookings / $total) * 100, 2) : 0,
            $total > 0 ? round(($row->pending_bookings / $total) * 100, 2) : 0,
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
