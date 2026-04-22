<?php

namespace App\Auth\Chain\Contracts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface AuthorizationHandler
{
    /**
     * Establece el siguiente handler en la cadena y lo retorna
     * para permitir encadenamiento fluido: $a->setNext($b)->setNext($c)
     */
    public function setNext(self $handler): self;

    /**
     * Procesa el request. Retorna true si la cadena completa pasa,
     * o un JsonResponse con el error correspondiente si falla.
     */
    public function handle(Request $request): true|JsonResponse;
}
