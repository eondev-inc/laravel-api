<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Design;
use App\Models\ProductVariation;
use App\Services\CartResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct(private CartResolver $resolver) {}

    /**
     * GET /api/cart — public (guest or authenticated).
     */
    public function show(Request $request): CartResource|JsonResponse
    {
        $cart = $this->resolver->resolve($request);

        if ($cart === null) {
            return response()->json(['message' => 'No cart session provided.'], 400);
        }

        return new CartResource($cart->load('items.variation.product', 'items.design'));
    }

    /**
     * POST /api/cart/items — add item to cart.
     */
    public function addItem(AddCartItemRequest $request): CartResource|JsonResponse
    {
        $cart = $this->resolver->resolve($request);

        if ($cart === null) {
            return response()->json(['message' => 'No cart session provided.'], 400);
        }

        $variation = ProductVariation::where('uuid', $request->product_variation_id)->firstOrFail();
        $design = null;

        if ($request->filled('design_id')) {
            $design = Design::where('uuid', $request->design_id)->firstOrFail();

            if (! $design->is_active && $design->user_id !== $cart->user_id) {
                return response()->json(['message' => 'Design not available.'], 403);
            }
        }

        $unitPrice = $this->calculateUnitPrice($variation, $design);

        $existingItem = $this->findEquivalentItem($cart, $variation->id, $design?->id);

        if ($existingItem) {
            $existingItem->increment('quantity', $request->input('quantity', 1));
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_variation_id' => $variation->id,
                'design_id' => $design?->id,
                'quantity' => $request->input('quantity', 1),
                'unit_price' => $unitPrice,
            ]);
        }

        return new CartResource($cart->fresh()->load('items.variation.product', 'items.design'));
    }

    /**
     * PUT /api/cart/items/{cartItem} — update item quantity.
     */
    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem): CartResource|JsonResponse
    {
        $cart = $this->resolver->resolve($request);

        if ($cart === null) {
            return response()->json(['message' => 'No cart session provided.'], 400);
        }

        if ($cartItem->cart_id !== $cart->id) {
            return response()->json(['message' => 'Item does not belong to this cart.'], 403);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return new CartResource($cart->fresh()->load('items.variation.product', 'items.design'));
    }

    /**
     * DELETE /api/cart/items/{cartItem} — remove item.
     */
    public function removeItem(Request $request, CartItem $cartItem): CartResource|JsonResponse
    {
        $cart = $this->resolver->resolve($request);

        if ($cart === null) {
            return response()->json(['message' => 'No cart session provided.'], 400);
        }

        if ($cartItem->cart_id !== $cart->id) {
            return response()->json(['message' => 'Item does not belong to this cart.'], 403);
        }

        $cartItem->delete();

        return new CartResource($cart->fresh()->load('items.variation.product', 'items.design'));
    }

    /**
     * POST /api/cart/merge — merge guest cart into authenticated user cart.
     */
    public function mergeCart(Request $request): CartResource|JsonResponse
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            return response()->json(['message' => 'Authentication required for merge.'], 401);
        }

        $sessionUuid = $request->header('X-Cart-Session');

        if (! $sessionUuid) {
            return response()->json(['message' => 'X-Cart-Session header is required.'], 400);
        }

        $guestCart = Cart::where('session_uuid', $sessionUuid)
            ->where('status', 'active')
            ->where('user_id', null)
            ->first();

        if (! $guestCart) {
            return response()->json(['message' => 'Guest cart not found or already merged.'], 404);
        }

        $userCart = Cart::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active'],
            ['session_uuid' => null]
        );

        DB::transaction(function () use ($guestCart, $userCart) {
            foreach ($guestCart->items as $guestItem) {
                $existing = $this->findEquivalentItem($userCart, $guestItem->product_variation_id, $guestItem->design_id);

                if ($existing) {
                    $existing->increment('quantity', $guestItem->quantity);
                } else {
                    CartItem::create([
                        'cart_id' => $userCart->id,
                        'product_variation_id' => $guestItem->product_variation_id,
                        'design_id' => $guestItem->design_id,
                        'quantity' => $guestItem->quantity,
                        'unit_price' => $guestItem->unit_price,
                    ]);
                }
            }

            $guestCart->update(['status' => 'abandoned']);
        });

        return new CartResource($userCart->fresh()->load('items.variation.product', 'items.design'));
    }

    // ─── Private helpers ─────────────────────────────────────────────────────────

    private function calculateUnitPrice(ProductVariation $variation, ?Design $design): float
    {
        $basePrice = $variation->price ?? $variation->product->price;

        return $basePrice + ($design?->price_modifier ?? 0);
    }

    private function findEquivalentItem(Cart $cart, int $variationId, ?int $designId): ?CartItem
    {
        return $cart->items()
            ->where('product_variation_id', $variationId)
            ->where('design_id', $designId)
            ->first();
    }
}
