<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductVariationRequest;
use App\Http\Requests\UpdateProductVariationRequest;
use App\Http\Resources\ProductVariationResource;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductVariationController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        $variations = $product->variations()
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(15);

        return ProductVariationResource::collection($variations);
    }

    public function show(Product $product, ProductVariation $variation): ProductVariationResource
    {
        abort_if($variation->product_id !== $product->id, 404);

        return new ProductVariationResource($variation);
    }

    public function store(StoreProductVariationRequest $request, Product $product): ProductVariationResource|JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        $variation = $product->variations()->create(
            $request->only('name', 'price', 'stock', 'is_active')
        );

        return (new ProductVariationResource($variation))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductVariationRequest $request, Product $product, ProductVariation $variation): ProductVariationResource|JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        abort_if($variation->product_id !== $product->id, 404);

        $variation->update($request->only('name', 'price', 'stock', 'is_active'));

        return new ProductVariationResource($variation);
    }

    public function destroy(Request $request, Product $product, ProductVariation $variation): JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        abort_if($variation->product_id !== $product->id, 404);

        $variation->delete();

        return response()->json(['message' => 'Variation deleted.'], 200);
    }
}
