<?php

namespace App\Auth\Chain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handler OR: pasa si el usuario tiene el rol requerido O el permiso requerido.
 * Si no tiene ninguno, corta la cadena con 403 Forbidden.
 *
 * Diseñado para escenarios donde admin puede hacer todo,
 * pero usuarios con el permiso específico también pueden pasar.
 *
 * Debe ir después de AuthenticatedHandler en la cadena.
 */
class RoleOrPermissionHandler extends AbstractHandler
{
    public function __construct(
        private readonly string $role,
        private readonly string $permission,
    ) {}

    public function handle(Request $request): true|JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole($this->role) || $user->hasPermission($this->permission)) {
            return $this->passToNext($request);
        }

        return new JsonResponse(
            ['message' => "Forbidden. Required role '{$this->role}' or permission '{$this->permission}'."],
            403
        );
    }
}
