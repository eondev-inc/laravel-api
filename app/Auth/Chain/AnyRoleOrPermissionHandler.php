<?php

namespace App\Auth\Chain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handler OR: pasa si el usuario tiene AL MENOS UNO de los roles o permisos dados.
 * Si no tiene ninguno, corta la cadena con 403 Forbidden.
 *
 * Soporta arrays vacíos para roles o permissions: si un array está vacío,
 * esa condición se ignora. Si ambos arrays tienen valores, se evalúa OR entre todos.
 *
 * Debe ir después de AuthenticatedHandler en la cadena.
 *
 * @param  array<string>  $roles  Lista de roles aceptados (OR entre ellos)
 * @param  array<string>  $permissions  Lista de permisos aceptados (OR entre ellos)
 */
class AnyRoleOrPermissionHandler extends AbstractHandler
{
    /**
     * @param  array<string>  $roles
     * @param  array<string>  $permissions
     */
    public function __construct(
        private readonly array $roles,
        private readonly array $permissions,
    ) {}

    public function handle(Request $request): true|JsonResponse
    {
        $user = $request->user();

        if ($user->hasAnyRole($this->roles) || $user->hasAnyPermission($this->permissions)) {
            return $this->passToNext($request);
        }

        $rolesStr = implode('|', $this->roles);
        $permsStr = implode('|', $this->permissions);

        return $this->deny(
            $request,
            "missing_any_role:[{$rolesStr}]_or_any_permission:[{$permsStr}]",
            403,
            'Forbidden. None of the required roles or permissions are present.',
        );
    }
}
