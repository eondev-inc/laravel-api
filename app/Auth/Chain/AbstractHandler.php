<?php

namespace App\Auth\Chain;

use App\Auth\Chain\Contracts\AuthorizationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Implementa el mecanismo de encadenamiento.
 * Los handlers concretos solo necesitan implementar handle()
 * y llamar a $this->passToNext($request) cuando su validación pasa.
 */
abstract class AbstractHandler implements AuthorizationHandler
{
    private ?AuthorizationHandler $next = null;

    public function setNext(AuthorizationHandler $handler): AuthorizationHandler
    {
        $this->next = $handler;

        // Retorna el handler recibido para permitir encadenamiento fluido:
        // $head->setNext(new HasRoleHandler('admin'))->setNext(new HasPermissionHandler('users.delete'))
        return $handler;
    }

    /**
     * Delega al siguiente handler si existe, o aprueba si es el último de la cadena.
     */
    protected function passToNext(Request $request): true|JsonResponse
    {
        if ($this->next === null) {
            return true;
        }

        return $this->next->handle($request);
    }
}
