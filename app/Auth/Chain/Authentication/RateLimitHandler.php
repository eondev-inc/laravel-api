<?php

namespace App\Auth\Chain\Authentication;

use App\Auth\Chain\AbstractHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Verifica que el usuario no haya superado el límite de intentos fallidos.
 * Usa Redis como store (vía CACHE_STORE=redis) para persistir los contadores.
 *
 * Configuración (en .env):
 *   LOGIN_MAX_ATTEMPTS=5
 *   LOGIN_LOCKOUT_MINUTES=15
 *
 * Key de Redis: login-attempts:{email}
 * Posición en la cadena: segundo — antes de validar password, para no gastar
 * recursos de BD si la cuenta ya está bloqueada.
 */
class RateLimitHandler extends AbstractHandler
{
    private int $maxAttempts;

    private int $lockoutSeconds;

    public function __construct()
    {
        $this->maxAttempts = (int) config('auth.login_max_attempts', 5);
        $this->lockoutSeconds = (int) config('auth.login_lockout_minutes', 15) * 60;
    }

    public function handle(Request $request): true|JsonResponse
    {
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return new JsonResponse([
                'message' => 'Demasiados intentos fallidos. Intenta de nuevo en '.$this->secondsToMinutes($seconds).'.)',
                'retry_after_seconds' => $seconds,
            ], 429, ['Retry-After' => $seconds]);
        }

        return $this->passToNext($request);
    }

    /**
     * Registra un intento fallido en Redis.
     * Se llama desde AuthController cuando la cadena no pasa.
     */
    public function hit(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), $this->lockoutSeconds);
    }

    /**
     * Limpia el contador. Se llama desde AuthController cuando el login es exitoso.
     */
    public function clear(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    private function throttleKey(Request $request): string
    {
        return 'login-attempts:'.strtolower((string) $request->input('email'));
    }

    private function secondsToMinutes(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);

        return $minutes === 1 ? '1 minuto' : "{$minutes} minutos";
    }
}
