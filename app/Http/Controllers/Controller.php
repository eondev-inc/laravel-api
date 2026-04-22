<?php

namespace App\Http\Controllers;

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
     *   AuthenticatedHandler → [HasRoleHandler] → [HasPermissionHandler]
     *
     * Retorna true si la cadena completa pasa, o un JsonResponse (401/403) si falla.
     *
     * Uso en un controller:
     *   $result = $this->authorize($request, role: 'admin', permission: 'users.delete');
     *   if ($result !== true) return $result;
     */
    protected function authorize(
        Request $request,
        string $role = '',
        string $permission = '',
    ): true|JsonResponse {
        $head = new AuthenticatedHandler;
        $tail = $head;

        if ($role !== '') {
            $tail = $tail->setNext(new HasRoleHandler($role));
        }

        if ($permission !== '') {
            $tail->setNext(new HasPermissionHandler($permission));
        }

        return $head->handle($request);
    }
}
