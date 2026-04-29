<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $auth = $this->authorize($request, role: ['admin'], permission: ['orders.view']);
        if ($auth !== true) {
            return $auth;
        }

        $query = Order::query()->with('user', 'items.variation', 'items.design');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource|JsonResponse
    {
        $auth = $this->authorize($request, role: ['admin'], permission: ['orders.view']);
        if ($auth !== true) {
            return $auth;
        }

        $order->load('user', 'items.variation', 'items.design');

        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource|JsonResponse
    {
        $auth = $this->authorize($request, role: ['admin'], permission: ['orders.manage']);
        if ($auth !== true) {
            return $auth;
        }

        $order->update(['status' => $request->input('status')]);

        return new OrderResource($order->load('items.variation', 'items.design'));
    }
}
