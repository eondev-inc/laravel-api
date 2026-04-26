<?php

namespace App\Auth\Chain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handler 1: Verifica que el request tenga un usuario autenticado.
 * Si no hay usuario, corta la cadena con 401 Unauthorized.
 */
class AuthenticatedHandler extends AbstractHandler
{
    public function handle(Request $request): true|JsonResponse
    {
        if ($request->user() === null) {
            return $this->deny($request, 'unauthenticated', 401, 'Unauthenticated.');
        }

        return $this->passToNext($request);
    }
}
