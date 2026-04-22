<?php

namespace App\Http\Controllers;

use App\Auth\Chain\Authentication\AccountActiveHandler;
use App\Auth\Chain\Authentication\CredentialsValidationHandler;
use App\Auth\Chain\Authentication\RateLimitHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * POST /api/login
     *
     * Pipeline CoR de autenticación:
     *   RateLimitHandler → CredentialsValidationHandler → AccountActiveHandler
     *
     * Si toda la cadena pasa:
     *   - limpia el contador de Redis
     *   - emite un token Sanctum
     *
     * Si algún eslabón falla:
     *   - registra un intento fallido en Redis
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $rateLimiter = new RateLimitHandler;

        // Construir la cadena
        $rateLimiter
            ->setNext(new CredentialsValidationHandler)
            ->setNext(new AccountActiveHandler);

        $result = $rateLimiter->handle($request);

        if ($result !== true) {
            // Registrar intento fallido si no fue un 429 (ya contabilizado por RateLimitHandler)
            if ($result->getStatusCode() !== 429) {
                $rateLimiter->hit($request);
            }

            return $result;
        }

        // Login exitoso — limpiar contador y emitir token
        $rateLimiter->clear($request);

        /** @var User $user */
        $user = $request->get('_resolved_user');

        $token = $user->createToken('api-token')->plainTextToken;

        return new JsonResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 200);
    }

    /**
     * POST /api/logout
     *
     * Revoca el token actual del usuario autenticado.
     * Requiere middleware auth:sanctum en la ruta.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return new JsonResponse(['message' => 'Sesión cerrada correctamente.'], 200);
    }
}
