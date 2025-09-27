<?php

namespace Modules\Service\Services;

use App\Services\LoggingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Enums\Roles;
use Modules\Service\DTOs\CreateServiceDto;
use Modules\Service\DTOs\UpdateServiceDto;
use Modules\Service\Models\Service;

/**
 * ServiceService
 *
 * @description Service class for managing service operations
 */
class ServiceService
{
    public function __construct(
        protected LoggingService $loggingService
    ) {}

    /**
     * Get all services
     *
     * @return LengthAwarePaginator
     * @throws \Throwable
     */
    public function getAllServices(Request $request)
    {
        $query = Service::with(['provider', 'category']);

        if ($request->has('q')) {
            $query->where('name', 'like', "%{$request->input('q')}%")
                ->orWhere('description', 'like', "%{$request->input('q')}%");
        }

        if (auth()->user()->role == Roles::class::PROVIDER) {
            $query->where('provider_id', auth()->id());
        } elseif (auth()->user()->role == Roles::class::ADMIN) {
            if ($request->has('provider_id')) {
                $query->where('provider_id', $request->input('provider_id'));
            }
        }

        if (auth()->user()->role != Roles::class::USER) {
            if ($request->has('status')) {
                $query->where('status', $request->boolean('status'));
            }
        } else {
            $query->where('status', 1);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        return $query->orderBy('name')->paginate(10);
    }

    /**
     * Create a new service
     *
     * @throws \Exception
     */
    public function createService(CreateServiceDto $serviceDto): Service
    {
        try {
            $service = Service::create($serviceDto->toArray());

            $this->loggingService->log('Service created', [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'provider_id' => $service->provider_id,
                'user_id' => Auth::id(),
            ]);

            return $service->load(['provider', 'category']);
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to create service', [
                'error' => $e->getMessage(),
                'service_name' => $serviceDto->name,
                'provider_id' => $serviceDto->provider_id,
                'user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Get a specific service by ID
     */
    public function getServiceById(Service $service): Service
    {
        return $service->load(['provider', 'category']);
    }

    /**
     * Update an existing service
     *
     * @throws \Exception
     */
    public function updateService(Service $service, UpdateServiceDto $serviceDto): Service
    {
        try {
            $service->update($serviceDto->toArray());

            $this->loggingService->log('Service updated', [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'provider_id' => $service->provider_id,
                'user_id' => Auth::id(),
            ]);

            return $service->load(['provider', 'category']);
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to update service', [
                'error' => $e->getMessage(),
                'service_id' => $service->id,
                'user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a service
     *
     * @throws \Exception
     */
    public function deleteService(Service $service): bool
    {
        try {
            $serviceId = $service->id;
            $serviceName = $service->name;
            $providerId = $service->provider_id;

            $deleted = $service->delete();

            $this->loggingService->log('Service deleted', [
                'service_id' => $serviceId,
                'service_name' => $serviceName,
                'provider_id' => $providerId,
                'user_id' => Auth::id(),
            ]);

            return $deleted;
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to delete service', [
                'error' => $e->getMessage(),
                'service_id' => $service->id,
                'user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Get services by provider
     */
    public function getServicesByProvider(int $providerId, Request $request): LengthAwarePaginator
    {
        $query = Service::where('provider_id', $providerId)
            ->with(['provider', 'category']);

        if ($request->has('q')) {
            $query->where('name', 'like', "%{$request->input('q')}%")
                ->orWhere('description', 'like', "%{$request->input('q')}%");
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        return $query->orderBy('name')->paginate(10);
    }

    /**
     * Get services by category
     */
    public function getServicesByCategory(int $categoryId, Request $request): LengthAwarePaginator
    {
        $query = Service::where('category_id', $categoryId)
            ->with(['provider', 'category']);

        if ($request->has('q')) {
            $query->where('name', 'like', "%{$request->input('q')}%")
                ->orWhere('description', 'like', "%{$request->input('q')}%");
        }

        if ($request->has('provider_id')) {
            $query->where('provider_id', $request->input('provider_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        return $query->orderBy('name')->paginate(10);
    }
}
