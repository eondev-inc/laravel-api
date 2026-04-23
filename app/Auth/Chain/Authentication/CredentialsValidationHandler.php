<?php

namespace App\Auth\Chain\Authentication;

use App\Auth\Chain\AbstractHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Valida las credenciales del usuario en un único paso:
 * busca el email y verifica el password. Si el email no existe,
 * ejecuta un hash dummy para evitar timing attacks (user enumeration).
 *
 * Retorna 401 con mensaje genérico en cualquier fallo de credenciales.
 *
 * Posición en la cadena: después de RateLimitHandler.
 */
class CredentialsValidationHandler extends AbstractHandler
{
    /**
     * Hash dummy para ejecutar el verificador cuando el usuario no existe,
     * evitando diferencias de tiempo que revelarían si el email está registrado.
     * Generado con un costo 12 (mismo que el default de Laravel bcrypt) para un
     * delay realista de validación de contraseñas.
     */
    private const DUMMY_HASH = '$2y$12$Z0pE4A9v/y.pGXYA0iQZ.eKzRj0L.uYw9.N9M0f.t/tM.1aT9.X2m';

    /** @var callable(string, string): bool */
    private $hashVerifier;

    /**
     * @param  callable(string, string): bool|null  $hashVerifier  Callable para verificar contraseñas.
     *                                                             Por defecto usa password_verify nativo.
     *                                                             Inyectable para tests de comportamiento.
     */
    public function __construct(?callable $hashVerifier = null)
    {
        $this->hashVerifier = $hashVerifier ?? 'password_verify';
    }

    public function handle(Request $request): true|JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if ($user === null) {
            // Ejecutar verificación dummy con la password enviada para evitar
            // timing-based user enumeration con costo de CPU real (Bcrypt costo 12)
            ($this->hashVerifier)($request->input('password') ?? str_repeat('a', 10), self::DUMMY_HASH);

            return new JsonResponse(['message' => 'Las credenciales proporcionadas son incorrectas.'], 401);
        }

        if (! ($this->hashVerifier)($request->input('password'), $user->password)) {
            return new JsonResponse(['message' => 'Las credenciales proporcionadas son incorrectas.'], 401);
        }

        // Adjuntar el usuario al request para handlers siguientes
        $request->merge(['_resolved_user' => $user]);

        return $this->passToNext($request);
    }
}
