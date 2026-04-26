<?php

namespace App\Http\Controllers;

use App\Auth\Chain\AnyRoleOrPermissionHandler;
use App\Auth\Chain\AuthenticatedHandler;
use App\Auth\Chain\HasPermissionHandler;
use App\Auth\Chain\HasRoleHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Construye y ejecuta el pipeline de autorización Chain of Responsibility.
     *
     * El orden de la cadena es siempre:
     *   AuthenticatedHandler → AnyRoleOrPermissionHandler (cuando hay arrays)
     *                       → HasRoleHandler (cuando role es string)
     *                       → HasPermissionHandler (cuando permission es string)
     *
     * Acepta string o array para role y permission:
     *   - string: requiere exactamente ese rol/permiso (AND con el otro parámetro)
     *   - array: requiere AL MENOS UNO de los roles/permisos (OR logic)
     *   - array + array: un único AnyRoleOrPermissionHandler con OR entre todos (roles OR permissions)
     *
     * Retorna true si la cadena completa pasa, o un JsonResponse (401/403) si falla.
     *
     * Uso en un controller:
     *   $result = $this->authorize($request, role: 'admin', permission: 'users.delete');
     *   $result = $this->authorize($request, role: ['admin', 'editor']);
     *   $result = $this->authorize($request, role: ['admin'], permission: ['users.view']);
     *   if ($result !== true) return $result;
     */
    protected function authorize(
        Request $request,
        string|array $role = '',
        string|array $permission = '',
    ): true|JsonResponse {
        $head = new AuthenticatedHandler;
        $tail = $head;

        $roleEmpty = $role === '' || $role === [];
        $permEmpty = $permission === '' || $permission === [];

        // Cuando ambos son arrays, los combina en un único handler OR (role OR permission)
        if (is_array($role) && is_array($permission) && ! $roleEmpty && ! $permEmpty) {
            $tail->setNext(new AnyRoleOrPermissionHandler(roles: $role, permissions: $permission));
        } else {
            if (! $roleEmpty) {
                if (is_array($role)) {
                    $tail = $tail->setNext(new AnyRoleOrPermissionHandler(roles: $role, permissions: []));
                } else {
                    $tail = $tail->setNext(new HasRoleHandler($role));
                }
            }

            if (! $permEmpty) {
                if (is_array($permission)) {
                    $tail->setNext(new AnyRoleOrPermissionHandler(roles: [], permissions: $permission));
                } else {
                    $tail->setNext(new HasPermissionHandler($permission));
                }
            }
        }

        return $head->handle($request);
    }
}
