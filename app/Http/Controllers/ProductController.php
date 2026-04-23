<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * GET /api/products — public.
     */
    public function index(): AnonymousResourceCollection
    {
        $products = Product::where('is_active', true)->with('category')->paginate(15);

        return ProductResource::collection($products);
    }

    /**
     * GET /api/products/{product} — public.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('category'));
    }

    /**
     * POST /api/products — admin only.
     */
    public function store(StoreProductRequest $request): ProductResource|JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        $category = Category::where('uuid', $request->category_id)->firstOrFail();

        $product = Product::create(array_merge(
            $request->only('name', 'slug', 'description', 'price', 'is_active'),
            ['category_id' => $category->id],
        ));

        return (new ProductResource($product->load('category')))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/products/{product} — admin only.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource|JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        $data = $request->only('name', 'slug', 'description', 'price', 'is_active');

        if ($request->filled('category_id')) {
            $category = Category::where('uuid', $request->category_id)->firstOrFail();
            $data['category_id'] = $category->id;
        }

        $product->update($data);

        return new ProductResource($product->load('category'));
    }

    /**
     * DELETE /api/products/{product} — admin only.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.'], 200);
    }
}
