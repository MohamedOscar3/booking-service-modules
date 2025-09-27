<?php

namespace Modules\Category\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Knuckles\Scribe\Attributes\Group;
use Modules\Category\DTOs\CategoryDTO;
use Modules\Category\Http\Requests\StoreCategoryRequest;
use Modules\Category\Http\Requests\UpdateCategoryRequest;
use Modules\Category\Http\Resources\CategoryResource;
use Modules\Category\Models\Category;
use Modules\Category\Services\CategoryService;

/**
 * Category API Controller
 *
 * @group Categories
 *
 * @description Manage categories through CRUD operations
 */
#[Group('Categories')]
class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     *
     * @group Categories
     *
     * @api {get} /api/categories Get all categories
     *
     * @apiName GetCategories
     *
     * @response 200 {
     *     "data": [
     *         {
     *             "id": 1,
     *             "name": "Technology",
     *             "user_id": 1,
     *             "created_at": "2025-09-25T08:40:31.000000Z",
     *             "updated_at": "2025-09-25T08:40:31.000000Z"
     *         }
     *     ],
     *     "message": "Categories retrieved successfully"
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function index(CategoryService $categoryService, ApiResponseService $apiResponseService, Request $request): JsonResponse
    {
        $categories = $categoryService->getAllCategories($request);

        return $apiResponseService->pagination(
            'Categories retrieved successfully',
            data: $categories,
            resource: CategoryResource::class
        );
    }

    /**
     * Store a newly created category
     *
     * @group Categories
     *
     * @api {post} /api/categories Create a new category
     *
     * @apiName CreateCategory
     *
     * @bodyParam name string required The category name. Must not exceed 255 characters. Example: Technology
     *
     * @response 201 {
     *     "data": {
     *         "id": 1,
     *         "name": "Technology",
     *         "user_id": 1,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T08:40:31.000000Z"
     *     },
     *     "message": "Category created successfully"
     * }
     * @response 422 {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "name": [
     *             "The category name is required."
     *         ]
     *     }
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function store(
        StoreCategoryRequest $request,
        ApiResponseService $apiResponseService,
        CategoryService $categoryService
    ): JsonResponse {

        try {
            $categoryDTO = new CategoryDTO(
                name: $request->validated()['name']
            );

            $category = $categoryService->createCategory($categoryDTO);

            return $apiResponseService->created(
                new CategoryResource($category),
                'Category created successfully'
            );
        } catch (\Exception $e) {
            return $apiResponseService->failedResponse('Failed to create category', 500);
        }
    }

    /**
     * Display the specified category
     *
     * @group Categories
     *
     * @api {get} /api/categories/{id} Get a specific category
     *
     * @apiName GetCategory
     *
     * @urlParam id integer required The category ID. Example: 1
     *
     * @response 200 {
     *     "data": {
     *         "id": 1,
     *         "name": "Technology",
     *         "user_id": 1,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T08:40:31.000000Z"
     *     },
     *     "message": "Category retrieved successfully"
     * }
     * @response 404 {
     *     "message": "Category not found"
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function show(
        Category $category,
        ApiResponseService $apiResponseService,
        CategoryService $categoryService
    ): JsonResponse {
        // Ensure user can only access their own categories
        if ($category->last_updated_by !== Auth::id()) {
            return $apiResponseService->failedResponse('Category not found', 404);
        }

        $category = $categoryService->getCategoryById($category);

        return $apiResponseService->successResponse(
            'Category retrieved successfully',
            200,
            new CategoryResource($category)
        );
    }

    /**
     * Update the specified category
     *
     * @group Categories
     *
     * @api {put} /api/categories/{id} Update a category
     *
     * @apiName UpdateCategory
     *
     * @urlParam id integer required The category ID. Example: 1
     * @bodyParam name string required The category name. Must not exceed 255 characters. Example: Updated Technology
     * @bodyParam _method string required The HTTP method. Example: PUT
     * @response 200 {
     *     "data": {
     *         "id": 1,
     *         "name": "Updated Technology",
     *         "user_id": 1,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T09:00:00.000000Z"
     *     },
     *     "message": "Category updated successfully"
     * }
     * @response 422 {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "name": [
     *             "The category name is required."
     *         ]
     *     }
     * }
     * @response 404 {
     *     "message": "Category not found"
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        ApiResponseService $apiResponseService,
        CategoryService $categoryService
    ): JsonResponse {
        try {
            $categoryDTO = new CategoryDTO(
                name: $request->validated()['name']
            );

            $updatedCategory = $categoryService->updateCategory($category, $categoryDTO);

            return $apiResponseService->successResponse(
                'Category updated successfully',
                200,
                new CategoryResource($updatedCategory)
            );
        } catch (\Exception $e) {
            return $apiResponseService->failedResponse('Failed to update category', 500);
        }
    }

    /**
     * Remove the specified category
     *
     * @group Categories
     *
     * @api {delete} /api/categories/{id} Delete a category
     *
     * @apiName DeleteCategory
     *
     * @urlParam id integer required The category ID. Example: 1
     *
     * @bodyParam _method string required The HTTP method. Example: DELETE
     *
     * @response 200 {
     *     "data": null,
     *     "message": "Category deleted successfully"
     * }
     * @response 404 {
     *     "message": "Category not found"
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function destroy(
        Category $category,
        ApiResponseService $apiResponseService,
        CategoryService $categoryService
    ): JsonResponse {
        try {
            $categoryService->deleteCategory($category);

            return $apiResponseService->successResponse(
                'Category deleted successfully',
                200,
                null
            );
        } catch (\Exception $e) {
            return $apiResponseService->failedResponse('Failed to delete category', null, 500);
        }
    }
}
