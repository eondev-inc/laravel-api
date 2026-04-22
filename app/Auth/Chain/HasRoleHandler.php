<?php

namespace App\Auth\Chain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handler 2: Verifica que el usuario autenticado tenga el rol requerido.
 * Si no tiene el rol, corta la cadena con 403 Forbidden.
 *
 * Debe ir después de AuthenticatedHandler en la cadena.
 */
class HasRoleHandler extends AbstractHandler
{
    public function __construct(private readonly string $role) {}

    public function handle(Request $request): true|JsonResponse
    {
        if (! $request->user()->hasRole($this->role)) {
            return new JsonResponse(['message' => 'Forbidden. Required role: '.$this->role], 403);
        }

        return $this->passToNext($request);
    }
}
