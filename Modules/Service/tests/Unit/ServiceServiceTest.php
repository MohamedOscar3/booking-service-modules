<?php

namespace Modules\Service\Tests\Unit;

use App\Services\LoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Auth\Enums\Roles;
use Modules\Auth\Models\User;
use Modules\Category\Models\Category;
use Modules\Service\DTOs\CreateServiceDto;
use Modules\Service\DTOs\UpdateServiceDto;
use Modules\Service\Models\Service;
use Modules\Service\Services\ServiceService;
use Tests\TestCase;

class ServiceServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private ServiceService $serviceService;

    private User $provider;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = User::factory()->create(['role' => Roles::PROVIDER]);
        $this->admin = User::factory()->create(['role' => Roles::ADMIN]);
        $this->category = Category::factory()->create(['last_updated_by' => $this->provider->id]);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')->andReturn(true);

        $this->serviceService = new ServiceService($mockLoggingService);
    }

    public function test_get_all_services_returns_paginated_results(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        Service::factory()->count(3)->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $request = new Request;
        $result = $this->serviceService->getAllServices($request);

        $this->assertNotNull($result);
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(3, $result->count());
    }

    public function test_get_all_services_filters_by_provider_for_provider_role(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        Service::factory()->count(3)->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);
        Service::factory()->count(2)->create(['category_id' => $this->category->id]); // Other provider services

        $request = new Request;
        $result = $this->serviceService->getAllServices($request);

        $this->assertEquals(3, $result->count()); // Should only see own services
    }

    public function test_get_all_services_shows_all_for_admin_role(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        Service::factory()->count(5)->create(['category_id' => $this->category->id]);

        $request = new Request;
        $result = $this->serviceService->getAllServices($request);

        $this->assertEquals(5, $result->count()); // Admin should see all services
    }

    public function test_get_all_services_with_search_query(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        Service::factory()->create(['name' => 'House Cleaning', 'provider_id' => $this->provider->id, 'category_id' => $this->category->id]);
        Service::factory()->create(['name' => 'Plumbing Service', 'provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $request = new Request(['q' => 'House']);
        $result = $this->serviceService->getAllServices($request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('House Cleaning', $result->first()->name);
    }

    public function test_get_all_services_with_category_filter(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $otherCategory = Category::factory()->create(['last_updated_by' => $this->provider->id]);

        Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);
        Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $otherCategory->id]);

        $request = new Request(['category_id' => $this->category->id]);
        $result = $this->serviceService->getAllServices($request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($this->category->id, $result->first()->category_id);
    }

    public function test_get_all_services_with_status_filter(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id, 'status' => true]);
        Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id, 'status' => false]);

        $request = new Request(['status' => '1']);
        $result = $this->serviceService->getAllServices($request);

        $this->assertEquals(1, $result->total());
        $this->assertTrue($result->first()->status);
    }

    public function test_get_all_services_admin_can_filter_by_provider(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);

        Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);
        Service::factory()->create(['provider_id' => $otherProvider->id, 'category_id' => $this->category->id]);

        $request = new Request(['provider_id' => $this->provider->id]);
        $result = $this->serviceService->getAllServices($request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($this->provider->id, $result->first()->provider_id);
    }

    public function test_get_all_services_loads_relationships(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $request = new Request;
        $result = $this->serviceService->getAllServices($request);

        $service = $result->first();
        $this->assertTrue($service->relationLoaded('provider'));
        $this->assertTrue($service->relationLoaded('category'));
    }

    public function test_create_service_successfully(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $serviceDto = new CreateServiceDto(
            name: 'Test Service',
            description: 'Test service description',
            duration: 120,
            price: 50.00,
            provider_id: $this->provider->id,
            category_id: $this->category->id,
            status: true
        );

        $result = $this->serviceService->createService($serviceDto);

        $this->assertInstanceOf(Service::class, $result);
        $this->assertEquals('Test Service', $result->name);
        $this->assertEquals('Test service description', $result->description);
        $this->assertEquals(120, $result->duration);
        $this->assertEquals(50.00, $result->price);
        $this->assertEquals($this->provider->id, $result->provider_id);
        $this->assertEquals($this->category->id, $result->category_id);
        $this->assertTrue($result->status);
        $this->assertTrue($result->relationLoaded('provider'));
        $this->assertTrue($result->relationLoaded('category'));

        $this->assertDatabaseHas('services', [
            'name' => 'Test Service',
            'description' => 'Test service description',
            'duration' => 120,
            'price' => 50.00,
            'provider_id' => $this->provider->id,
            'category_id' => $this->category->id,
            'status' => true,
        ]);
    }

    public function test_create_service_logs_creation(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Service created', Mockery::type('array'));

        $serviceService = new ServiceService($mockLoggingService);
        $serviceDto = new CreateServiceDto(
            name: 'Test Service',
            description: 'Test service description',
            duration: 120,
            price: 50.00,
            provider_id: $this->provider->id,
            category_id: $this->category->id,
            status: true
        );

        $serviceService->createService($serviceDto);
    }

    public function test_create_service_throws_exception_and_logs_error(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Failed to create service', Mockery::type('array'));

        // Mock Service to throw exception
        $this->partialMock(Service::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new \Exception('Database error'));
        });

        $serviceService = new ServiceService($mockLoggingService);
        $serviceDto = new CreateServiceDto(
            name: 'Test Service',
            description: 'Test service description',
            duration: 120,
            price: 50.00,
            provider_id: $this->provider->id,
            category_id: $this->category->id,
            status: true
        );

        $this->expectException(\Exception::class);
        $serviceService->createService($serviceDto);
    }

    public function test_get_service_by_id_loads_relationships(): void
    {
        $service = Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $result = $this->serviceService->getServiceById($service);

        $this->assertEquals($service->id, $result->id);
        $this->assertTrue($result->relationLoaded('provider'));
        $this->assertTrue($result->relationLoaded('category'));
    }

    public function test_update_service_successfully(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $service = Service::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original description',
            'duration' => 60,
            'price' => 25.00,
            'provider_id' => $this->provider->id,
            'category_id' => $this->category->id,
            'status' => false,
        ]);

        $serviceDto = new UpdateServiceDto(
            name: 'Updated Name',
            description: 'Updated description',
            duration: 120,
            price: 50.00,
            category_id: $this->category->id,
            status: true
        );

        $result = $this->serviceService->updateService($service, $serviceDto);

        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals('Updated description', $result->description);
        $this->assertEquals(120, $result->duration);
        $this->assertEquals(50.00, $result->price);
        $this->assertTrue($result->status);
        $this->assertTrue($result->relationLoaded('provider'));
        $this->assertTrue($result->relationLoaded('category'));

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'duration' => 120,
            'price' => 50.00,
            'status' => true,
        ]);
    }

    public function test_update_service_with_partial_data(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $service = Service::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original description',
            'provider_id' => $this->provider->id,
            'category_id' => $this->category->id,
        ]);

        $serviceDto = new UpdateServiceDto(
            name: 'Updated Name',
            description: null,
            duration: null,
            price: null,
            category_id: null,
            status: null
        );

        $result = $this->serviceService->updateService($service, $serviceDto);

        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals('Original description', $result->description); // Should remain unchanged
    }

    public function test_update_service_logs_update(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $service = Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Service updated', Mockery::type('array'));

        $serviceService = new ServiceService($mockLoggingService);
        $serviceDto = new UpdateServiceDto(
            name: 'Updated Name',
            description: null,
            duration: null,
            price: null,
            category_id: null,
            status: null
        );

        $serviceService->updateService($service, $serviceDto);
    }

    public function test_update_service_throws_exception_and_logs_error(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $service = Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Failed to update service', Mockery::type('array'));

        // Force the service to throw an exception on update
        $mockService = Mockery::mock(Service::class);
        $mockService->shouldReceive('update')->andThrow(new \Exception('Database error'));
        $mockService->shouldReceive('getAttribute')->andReturn($service->id, $service->name, $service->provider_id);

        $serviceService = new ServiceService($mockLoggingService);
        $serviceDto = new UpdateServiceDto(
            name: 'Updated Name',
            description: null,
            duration: null,
            price: null,
            category_id: null,
            status: null
        );

        $this->expectException(\Exception::class);
        $serviceService->updateService($mockService, $serviceDto);
    }

    public function test_delete_service_successfully(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $service = Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $result = $this->serviceService->deleteService($service);

        $this->assertTrue($result);
        $this->assertSoftDeleted('services', ['id' => $service->id]);
    }

    public function test_delete_service_logs_deletion(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $service = Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Service deleted', Mockery::type('array'));

        $serviceService = new ServiceService($mockLoggingService);

        $serviceService->deleteService($service);
    }

    public function test_delete_service_throws_exception_and_logs_error(): void
    {
        Sanctum::actingAs($this->provider, ['*']);

        $service = Service::factory()->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Failed to delete service', Mockery::type('array'));

        // Force the service to throw an exception on delete
        $mockService = Mockery::mock(Service::class);
        $mockService->shouldReceive('delete')->andThrow(new \Exception('Database error'));
        $mockService->shouldReceive('getAttribute')->andReturn($service->id, $service->name, $service->provider_id);

        $serviceService = new ServiceService($mockLoggingService);

        $this->expectException(\Exception::class);
        $serviceService->deleteService($mockService);
    }

    public function test_get_services_by_provider_returns_filtered_results(): void
    {
        $otherProvider = User::factory()->create(['role' => Roles::PROVIDER]);

        Service::factory()->count(3)->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);
        Service::factory()->count(2)->create(['provider_id' => $otherProvider->id, 'category_id' => $this->category->id]);

        $request = new Request;
        $result = $this->serviceService->getServicesByProvider($this->provider->id, $request);

        $this->assertEquals(3, $result->total());
        foreach ($result as $service) {
            $this->assertEquals($this->provider->id, $service->provider_id);
        }
    }

    public function test_get_services_by_provider_with_filters(): void
    {
        Service::factory()->create(['name' => 'House Cleaning', 'provider_id' => $this->provider->id, 'category_id' => $this->category->id]);
        Service::factory()->create(['name' => 'Plumbing', 'provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $request = new Request(['q' => 'House']);
        $result = $this->serviceService->getServicesByProvider($this->provider->id, $request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('House Cleaning', $result->first()->name);
    }

    public function test_get_services_by_category_returns_filtered_results(): void
    {
        $otherCategory = Category::factory()->create(['last_updated_by' => $this->provider->id]);

        Service::factory()->count(3)->create(['provider_id' => $this->provider->id, 'category_id' => $this->category->id]);
        Service::factory()->count(2)->create(['provider_id' => $this->provider->id, 'category_id' => $otherCategory->id]);

        $request = new Request;
        $result = $this->serviceService->getServicesByCategory($this->category->id, $request);

        $this->assertEquals(3, $result->total());
        foreach ($result as $service) {
            $this->assertEquals($this->category->id, $service->category_id);
        }
    }

    public function test_get_services_by_category_with_filters(): void
    {
        Service::factory()->create(['name' => 'House Cleaning', 'provider_id' => $this->provider->id, 'category_id' => $this->category->id]);
        Service::factory()->create(['name' => 'Plumbing', 'provider_id' => $this->provider->id, 'category_id' => $this->category->id]);

        $request = new Request(['q' => 'House']);
        $result = $this->serviceService->getServicesByCategory($this->category->id, $request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('House Cleaning', $result->first()->name);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
