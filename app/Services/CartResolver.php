<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;

class CartResolver
{
    /**
     * Resolve or create a cart based on the authenticated user or X-Cart-Session header.
     *
     * Priority: authenticated user > X-Cart-Session header.
     * Returns null if neither is available (guest with no session).
     */
    public function resolve(Request $request): ?Cart
    {
        $user = auth('sanctum')->user();

        if ($user instanceof User) {
            return Cart::firstOrCreate(
                ['user_id' => $user->id, 'status' => 'active'],
                ['session_uuid' => null]
            );
        }

        $sessionUuid = $request->header('X-Cart-Session');

        if ($sessionUuid) {
            return Cart::firstOrCreate(
                ['session_uuid' => $sessionUuid, 'user_id' => null],
                ['status' => 'active']
            );
        }

        return null;
    }
}
