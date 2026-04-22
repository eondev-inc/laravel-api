<?php

namespace App\Auth\Chain\Authentication;

use App\Auth\Chain\AbstractHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verifica que la cuenta del usuario esté activa (is_active = true).
 * Retorna 403 si la cuenta está desactivada por un administrador.
 *
 * Depende de que CredentialsValidationHandler ya haya adjuntado '_resolved_user' al request.
 * Posición en la cadena: último — solo se evalúa si las credenciales son correctas,
 * para no revelar si una cuenta inactiva existe a través del error.
 */
class AccountActiveHandler extends AbstractHandler
{
    public function handle(Request $request): true|JsonResponse
    {
        $user = $request->get('_resolved_user');

        if (! $user->is_active) {
            return new JsonResponse(['message' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'], 403);
        }

        return $this->passToNext($request);
    }
}
