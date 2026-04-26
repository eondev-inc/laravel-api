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
use Illuminate\Support\Facades\Log;

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
            Log::error('Checkout gateway error during create', [
                'exception' => $e,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'El pago no pudo procesarse. Por favor, inténtalo de nuevo.'], 422);
        }
    }

    /**
     * GET|POST /api/checkout/commit
     * Handle Transbank Webpay callback and update order status.
     *
     * Transbank sends two types of callbacks:
     * 1. Normal flow: token_ws (GET/POST) — payment completed or failed at bank
     * 2. Cancellation: TBK_TOKEN + TBK_MAC (POST) — user cancelled before bank
     *
     * TBK_MAC must be verified before processing cancellation callbacks.
     */
    public function commit(Request $request): RedirectResponse
    {
        $tokenWs = $request->input('token_ws');
        $tbkToken = $request->input('TBK_TOKEN');
        $tbkMac = $request->input('TBK_MAC');

        // Detect Transbank cancellation callback (TBK_TOKEN present, no token_ws)
        if ($tbkToken && ! $tokenWs) {
            if (! $this->verifyTbkMac($request, $tbkMac)) {
                Log::warning('TBK_MAC signature verification failed', [
                    'tbk_token' => $tbkToken,
                    'order' => $request->input('TBK_ORDEN_COMPRA'),
                    'session' => $request->input('TBK_ID_SESSION'),
                ]);

                return redirect(config('app.frontend_url').'/checkout/failure');
            }

            // Valid cancellation — mark order as failed
            $order = Order::where('token_ws', $tbkToken)->first();
            if ($order && $order->status === 'pending') {
                $order->update(['status' => 'failed']);
            }

            return redirect(config('app.frontend_url').'/checkout/failure');
        }

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

    /**
     * Verifica la firma TBK_MAC enviada por Transbank en callbacks de cancelación.
     *
     * El MAC se calcula como HMAC-SHA256 de los parámetros TBK_* concatenados,
     * usando la api_key_secret configurada para el comercio.
     */
    private function verifyTbkMac(Request $request, ?string $tbkMac): bool
    {
        if (! $tbkMac) {
            return false;
        }

        $apiKeySecret = config('services.transbank.api_key_secret');

        // Sin clave configurada no se puede verificar — rechazar por seguridad
        if (! $apiKeySecret) {
            return false;
        }

        // Construir el mensaje a firmar con los campos TBK en orden canónico
        $tbkOrden = $request->input('TBK_ORDEN_COMPRA', '');
        $tbkSession = $request->input('TBK_ID_SESSION', '');
        $tbkToken = $request->input('TBK_TOKEN', '');

        $message = $tbkOrden.$tbkSession.$tbkToken;
        $expectedMac = hash_hmac('sha256', $message, $apiKeySecret);

        return hash_equals($expectedMac, $tbkMac);
    }
}
