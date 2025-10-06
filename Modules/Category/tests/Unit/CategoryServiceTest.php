<?php

namespace Modules\Category\Tests\Unit;

use App\Services\LoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Auth\Models\User;
use Modules\Category\DTOs\CategoryDTO;
use Modules\Category\Models\Category;
use Modules\Category\Services\CategoryService;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private CategoryService $categoryService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')->andReturn(true);

        $this->categoryService = new CategoryService($mockLoggingService);
    }

    public function test_get_all_categories_returns_paginated_results(): void
    {
        Category::factory()->count(3)->create(['last_updated_by' => $this->user->id]);

        $request = new Request;
        $result = $this->categoryService->getAllCategories($request);

        $this->assertNotNull($result);
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->count()); // paginate(1) in service
    }

    public function test_get_all_categories_with_search_query(): void
    {
        Category::factory()->create(['name' => 'Technology', 'last_updated_by' => $this->user->id]);
        Category::factory()->create(['name' => 'Health', 'last_updated_by' => $this->user->id]);

        $request = new Request(['q' => 'Tech']);
        $result = $this->categoryService->getAllCategories($request);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Technology', $result->first()->name);
    }

    public function test_get_all_categories_loads_relationships(): void
    {
        Category::factory()->create(['last_updated_by' => $this->user->id]);

        $request = new Request;
        $result = $this->categoryService->getAllCategories($request);

        $category = $result->first();
        $this->assertTrue($category->relationLoaded('lastUpdatedByUser'));
    }

    public function test_create_category_successfully(): void
    {
        $categoryDTO = new CategoryDTO('Test Category');

        $result = $this->categoryService->createCategory($categoryDTO);

        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals('Test Category', $result->name);
        $this->assertEquals($this->user->id, $result->last_updated_by);
        $this->assertTrue($result->relationLoaded('lastUpdatedByUser'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'last_updated_by' => $this->user->id,
        ]);
    }


    public function test_create_category_logs_creation(): void
    {
        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Category created', Mockery::type('array'));

        $categoryService = new CategoryService($mockLoggingService);
        $categoryDTO = new CategoryDTO('Test Category');

        $categoryService->createCategory($categoryDTO);
    }



    public function test_create_category_throws_exception_and_logs_error(): void
    {
        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Failed to create category', Mockery::type('array'));

        // Force database error by using invalid data
        $categoryService = new CategoryService($mockLoggingService);

        // Mock Category to throw exception
        $this->partialMock(Category::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new \Exception('Database error'));
        });

        $categoryDTO = new CategoryDTO('Test Category');

        $this->expectException(\Exception::class);
        $categoryService->createCategory($categoryDTO);
    }

    public function test_get_trashed_category_returns_trashed_category(): void
    {
        $category = Category::factory()->create(['name' => 'Test Category', 'last_updated_by' => $this->user->id]);
        $category->delete();

        $result = $this->categoryService->getTrashedCategory('Test');

        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals('Test Category', $result->name);
        $this->assertNotNull($result->deleted_at);
    }

    public function test_get_trashed_category_returns_null_when_not_found(): void
    {
        $result = $this->categoryService->getTrashedCategory('NonExistent');

        $this->assertNull($result);
    }

    public function test_restore_trashed_category_if_exist_restores_category(): void
    {
        $category = Category::factory()->create(['name' => 'Test Category', 'last_updated_by' => $this->user->id]);
        $category->delete();

        $result = $this->categoryService->restoreTrashedCategoryIfExist('Test Category');

        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals('Test Category', $result->name);
        $this->assertNull($result->deleted_at);
        $this->assertEquals($this->user->id, $result->last_updated_by);
    }

    public function test_restore_trashed_category_if_exist_returns_null_when_not_found(): void
    {
        $result = $this->categoryService->restoreTrashedCategoryIfExist('NonExistent');

        $this->assertNull($result);
    }

    public function test_get_category_by_id_loads_relationships(): void
    {
        $category = Category::factory()->create(['last_updated_by' => $this->user->id]);

        $result = $this->categoryService->getCategoryById($category);

        $this->assertEquals($category->id, $result->id);
        $this->assertTrue($result->relationLoaded('lastUpdatedByUser'));
    }

    public function test_update_category_successfully(): void
    {
        $category = Category::factory()->create(['name' => 'Original Name', 'last_updated_by' => $this->user->id]);
        $categoryDTO = new CategoryDTO('Updated Name');

        $result = $this->categoryService->updateCategory($category, $categoryDTO);

        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals($this->user->id, $result->last_updated_by);
        $this->assertTrue($result->relationLoaded('lastUpdatedByUser'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'last_updated_by' => $this->user->id,
        ]);
    }

    public function test_update_category_logs_update(): void
    {
        $category = Category::factory()->create(['last_updated_by' => $this->user->id]);

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Category updated', Mockery::type('array'));

        $categoryService = new CategoryService($mockLoggingService);
        $categoryDTO = new CategoryDTO('Updated Name');

        $categoryService->updateCategory($category, $categoryDTO);
    }

    public function test_update_category_throws_exception_and_logs_error(): void
    {
        $category = Category::factory()->forUser($this->user)->create();

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Failed to update category', Mockery::type('array'));

        // Force the category to throw an exception on update
        $mockCategory = Mockery::mock(Category::class);
        $mockCategory->shouldReceive('update')->andThrow(new \Exception('Database error'));
        $mockCategory->shouldReceive('getAttribute')->andReturn($category->id);

        $categoryService = new CategoryService($mockLoggingService);
        $categoryDTO = new CategoryDTO('Updated Name');

        $this->expectException(\Exception::class);
        $categoryService->updateCategory($mockCategory, $categoryDTO);
    }

    public function test_delete_category_successfully(): void
    {
        $category = Category::factory()->create(['last_updated_by' => $this->user->id]);

        $result = $this->categoryService->deleteCategory($category);

        $this->assertTrue($result);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }


    public function test_delete_category_throws_exception_and_logs_error(): void
    {
        $category = Category::factory()->forUser($this->user)->create();

        $mockLoggingService = Mockery::mock(LoggingService::class);
        $mockLoggingService->shouldReceive('log')
            ->once()
            ->with('Failed to delete category', Mockery::type('array'));

        // Force the category to throw an exception on delete
        $mockCategory = Mockery::mock(Category::class);
        $mockCategory->shouldReceive('delete')->andThrow(new \Exception('Database error'));
        $mockCategory->shouldReceive('getAttribute')->andReturn($category->id, $category->name);

        $categoryService = new CategoryService($mockLoggingService);

        $this->expectException(\Exception::class);
        $categoryService->deleteCategory($mockCategory);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
