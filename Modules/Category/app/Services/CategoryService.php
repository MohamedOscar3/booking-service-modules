<?php

namespace Modules\Category\Services;

use App\Services\LoggingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Category\DTOs\CategoryDTO;
use Modules\Category\Models\Category;

/**
 * CategoryService
 *
 * @description Service class for managing category operations
 */
class CategoryService
{
    public function __construct(
        protected LoggingService $loggingService
    ) {}

    /**
     * Get all categories
     *
     * @return LengthAwarePaginator
     * @throws \Throwable
     */
    public function getAllCategories(Request $request)
    {
        $query = Category::with('lastUpdatedByUser');

        if ($request->has('q')) {
            $query->where('name', 'like', "%{$request->input('q')}%");
        }

        return $query->orderBy('name')->paginate(1);
    }

    /**
     * Create a new category
     *
     * @throws \Exception
     */
    public function createCategory(CategoryDTO $categoryDTO): Category
    {

        try {
            $trashedCategory = $this->restoreTrashedCategoryIfExist($categoryDTO->name);

            if ($trashedCategory != null) {
                $this->loggingService->log('Category restored', [
                    'category_id' => $trashedCategory->id,
                    'category_name' => $trashedCategory->name,
                    'user_id' => Auth::id(),
                ]);

                return $trashedCategory;
            }

            $category = Category::create([
                'name' => $categoryDTO->name,
                'last_updated_by' => auth()->id(),
            ]);

            $this->loggingService->log('Category created', [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'user_id' => Auth::id(),
            ]);

            return $category->load('lastUpdatedByUser');
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to create category', [
                'error' => $e->getMessage(),
                'category_name' => $categoryDTO->name,
                'user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }

    public function restoreTrashedCategoryIfExist($name): ?Category
    {
        $trashedCategory = $this->getTrashedCategory($name);
        if ($trashedCategory != null) {
            $trashedCategory->deleted_at = null;
            $trashedCategory->last_updated_by = Auth::id();
            $trashedCategory->save();

            $this->loggingService->log('Category restored', [
                'category_id' => $trashedCategory->id,
                'category_name' => $trashedCategory->name,
                'user_id' => Auth::id(),
            ]);
        }

        return $trashedCategory;
    }

    public function getTrashedCategory($keyword = null): ?Category
    {
        $query = Category::onlyTrashed();
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        return $query->first();
    }

    /**
     * Get a specific category by ID
     */
    public function getCategoryById(Category $category): Category
    {
        return $category->load('lastUpdatedByUser');
    }

    /**
     * Update an existing category
     *
     * @throws \Exception
     */
    public function updateCategory(Category $category, CategoryDTO $categoryDTO): Category
    {
        try {
            $category->update([
                'name' => $categoryDTO->name,
                'last_updated_by' => Auth::id(),
            ]);

            $this->loggingService->log('Category updated', [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'user_id' => Auth::id(),
            ]);

            return $category->load('lastUpdatedByUser');
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to update category', [
                'error' => $e->getMessage(),
                'category_id' => $category->id,
                'user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a category
     *
     * @throws \Exception
     */
    public function deleteCategory(Category $category): bool
    {
        try {
            $categoryId = $category->id;
            $categoryName = $category->name;

            $deleted = $category->delete();

            $this->loggingService->log('Category deleted', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user_id' => Auth::id(),
            ]);

            return $deleted;
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to delete category', [
                'error' => $e->getMessage(),
                'category_id' => $category->id,
                'user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }
}
