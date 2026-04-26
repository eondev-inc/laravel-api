<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asigna un correlation ID único a cada request.
 *
 * Si el request trae el header X-Request-ID, lo reutiliza (upstream trace).
 * Si no, genera un UUID v4 nuevo.
 *
 * El ID se añade a la respuesta y se comparte con el contexto de logs
 * a través de Log::withContext().
 */
class SetRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID') ?: (string) Str::uuid();

        // Compartir con todos los logs de este request
        Log::withContext(['request_id' => $requestId]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
