<?php

use App\Auth\Chain\AbstractHandler;
use App\Auth\Chain\AuthenticatedHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

describe('AuthenticatedHandler', function () {
    it('returns 401 when no user is authenticated', function () {
        $request = Request::create('/');
        // Sin usuario en el request (user() retorna null)

        $handler = new AuthenticatedHandler;
        $result = $handler->handle($request);

        expect($result)->not->toBeTrue();
        expect($result->getStatusCode())->toBe(401);
    });

    it('returns true when user is authenticated and no next handler exists', function () {
        $user = new User(['name' => 'Test', 'email' => 'test@test.com']);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $handler = new AuthenticatedHandler;
        $result = $handler->handle($request);

        expect($result)->toBeTrue();
    });

    it('delegates to next handler when authenticated', function () {
        $user = new User(['name' => 'Test', 'email' => 'test@test.com']);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        // Encadenamos un segundo handler que siempre falla
        $nextHandler = new class extends AbstractHandler
        {
            public function handle(Request $request): true|JsonResponse
            {
                return new JsonResponse(['message' => 'next called'], 403);
            }
        };

        $handler = new AuthenticatedHandler;
        $handler->setNext($nextHandler);

        $result = $handler->handle($request);

        // El primer handler pasó, el segundo cortó con 403
        expect($result->getStatusCode())->toBe(403);
    });
});
