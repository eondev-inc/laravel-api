<?php

namespace App\Http\Controllers;

use App\Contracts\Payments\TransbankGateway;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(private TransbankGateway $gateway) {}

    /**
     * POST /api/checkout
     * Convert active cart to a pending order and initialize Webpay transaction.
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $result = DB::transaction(function () use ($user) {
            $cart = Cart::where('user_id', $user->id)
                ->where('status', 'active')
                ->with('items.variation.product')
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw new \Exception('No active cart with items found.');
            }

            $subtotal = $cart->items->sum(fn ($item) => $item->unit_price * $item->quantity);

            $order = Order::create([
                'user_id' => $user->id,
                'cart_id' => $cart->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variation_id' => $item->product_variation_id,
                    'design_id' => $item->design_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->unit_price * $item->quantity,
                ]);
            }

            $cart->update(['status' => 'converted']);

            $returnUrl = route('checkout.commit');
            $gatewayResult = $this->gateway->create(
                buyOrder: (string) $order->id,
                sessionId: $order->uuid,
                amount: (float) $order->total,
                returnUrl: $returnUrl,
            );

            $order->update([
                'token_ws' => $gatewayResult['token'],
                'webpay_url' => $gatewayResult['url'],
            ]);

            return $gatewayResult;
        });

        return response()->json([
            'token_ws' => $result['token'],
            'url' => $result['url'],
        ]);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}

    /**
     * GET|POST /api/checkout/commit
     * Handle Transbank Webpay callback and update order status.
     */
    public function commit(Request $request): RedirectResponse
    {
        $tokenWs = $request->input('token_ws') ?? $request->input('TBK_TOKEN');

        if (! $tokenWs) {
            return redirect(config('app.frontend_url').'/checkout/failure');
        }

        $order = Order::where('token_ws', $tokenWs)->first();

        if (! $order) {
            return redirect(config('app.frontend_url').'/checkout/failure');
        }

        if ($order->status !== 'pending') {
            $path = $order->status === 'paid' ? '/checkout/success' : '/checkout/failure';

            return redirect(config('app.frontend_url').$path);
        }

        try {
            $result = $this->gateway->commit($tokenWs);
            $success = isset($result['response_code']) && $result['response_code'] === 0;
        } catch (\Exception) {
            $success = false;
        }

        $order->update(['status' => $success ? 'paid' : 'failed']);

        $path = $success ? '/checkout/success' : '/checkout/failure';

        return redirect(config('app.frontend_url').$path);
    }
}
