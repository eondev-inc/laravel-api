<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignRequest;
use App\Http\Resources\DesignResource;
use App\Models\Design;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DesignController extends Controller
{
    /**
     * GET /api/designs — public.
     */
    public function index(): AnonymousResourceCollection
    {
        $designs = Design::where('is_active', true)->with('product')->paginate(15);

        return DesignResource::collection($designs);
    }

    /**
     * GET /api/designs/{design} — public.
     */
    public function show(Design $design): DesignResource
    {
        return new DesignResource($design->load('product'));
    }

    /**
     * POST /api/designs — authenticated + rate-limited (via route middleware).
     * Handles image upload and storage.
     */
    public function store(StoreDesignRequest $request): DesignResource|JsonResponse
    {
        $auth = $this->authorize($request);
        if ($auth !== true) {
            return $auth;
        }

        $product = Product::where('uuid', $request->product_id)->firstOrFail();

        $file = $request->file('image');
        $extension = strtolower($file->guessExtension() ?? 'png');
        $path = Storage::disk('s3_private')->putFileAs('designs', $file, Str::random(40).'.'.$extension);

        $design = Design::create([
            'name' => $request->name,
            'file_path' => $path,
            'file_extension' => $extension,
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
        ]);

        return (new DesignResource($design->load('product')))->response()->setStatusCode(201);
    }

    /**
     * DELETE /api/designs/{design} — admin only.
     */
    public function destroy(Request $request, Design $design): JsonResponse
    {
        $auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
        if ($auth !== true) {
            return $auth;
        }

        Storage::disk('s3_private')->delete($design->file_path);
        $design->delete();

        return response()->json(['message' => 'Design deleted.'], 200);
    }
}
