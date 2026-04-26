<?php

namespace App\Auth\Chain\Authentication;

use App\Auth\Chain\AbstractHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verifica que la cuenta del usuario esté activa (is_active = true).
 * Retorna 403 si la cuenta está desactivada por un administrador.
 *
 * Depende de que CredentialsValidationHandler ya haya adjuntado '_resolved_user' al request.
 * Posición en la cadena: último — solo se evalúa si las credenciales son correctas,
 * para no revelar si una cuenta inactiva existe a través del error.
 *
 * Validación defensiva: retorna 403 (fail-closed) si '_resolved_user' está ausente o no es un User,
 * lo que indicaría un bug en la composición de la cadena (orden incorrecto de handlers).
 */
class AccountActiveHandler extends AbstractHandler
{
    public function handle(Request $request): true|JsonResponse
    {
        $user = $request->get('_resolved_user');

        // Validación defensiva: si no existe o no es un User, falla cerrado con 403
        if (! ($user instanceof User)) {
            return new JsonResponse(
                ['message' => 'Forbidden.'],
                403
            );
        }

        if (! $user->is_active) {
            return new JsonResponse(['message' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'], 403);
        }

        return $this->passToNext($request);
    }
}
