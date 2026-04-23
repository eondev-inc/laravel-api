<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * GET /api/categories — public.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::where('is_active', true)->paginate(15);

        return CategoryResource::collection($categories);
    }

    /**
     * GET /api/categories/{category} — public.
     */
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    /**
     * POST /api/categories — admin only.
     */
    public function store(StoreCategoryRequest $request): CategoryResource|JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        $category = Category::create($request->only('name', 'slug', 'is_active'));

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/categories/{category} — admin only.
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource|JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        $category->update($request->only('name', 'slug', 'is_active'));

        return new CategoryResource($category);
    }

    /**
     * DELETE /api/categories/{category} — admin only.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.'], 200);
    }
}
