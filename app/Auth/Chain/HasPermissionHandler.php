<?php

namespace App\Auth\Chain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handler 3: Verifica que el usuario autenticado tenga el permiso requerido
 * a través de alguno de sus roles.
 * Si no tiene el permiso, corta la cadena con 403 Forbidden.
 *
 * Debe ir después de AuthenticatedHandler en la cadena.
 */
class HasPermissionHandler extends AbstractHandler
{
    public function __construct(private readonly string $permission) {}

    public function handle(Request $request): true|JsonResponse
    {
        if (! $request->user()->hasPermission($this->permission)) {
            return $this->deny($request, 'missing_permission:'.$this->permission, 403, 'Forbidden. Required permission: '.$this->permission);
        }

        return $this->passToNext($request);
    }
}
