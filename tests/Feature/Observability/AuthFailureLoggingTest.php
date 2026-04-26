<?php

use App\Auth\Chain\AuthenticatedHandler;
use App\Auth\Chain\HasRoleHandler;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

describe('Auth failure logging — AuthenticatedHandler', function () {
    it('logs warning with auth_failure when user is null', function () {
        Log::spy();

        $request = Request::create('/api/users', 'GET');
        // No user set — $request->user() returns null

        $handler = new AuthenticatedHandler;
        $result = $handler->handle($request);

        expect($result->getStatusCode())->toBe(401);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'auth_failure')
                    && $context['status'] === 401
                    && $context['user_id'] === null
                    && isset($context['reason'])
                    && isset($context['path'])
                    && isset($context['method']);
            });
    });

    it('logs warning with reason field for unauthenticated failure', function () {
        Log::spy();

        $request = Request::create('/api/secret', 'POST');
        $handler = new AuthenticatedHandler;
        $handler->handle($request);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $context['reason'] === 'unauthenticated';
            });
    });
});

describe('Auth failure logging — HasRoleHandler (feature)', function () {
    it('logs warning when authenticated user lacks required role', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        Log::spy();

        $this->getJson('/api/users')->assertStatus(403);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($user) {
                return str_contains($message, 'auth_failure')
                    && $context['status'] === 403
                    && $context['user_id'] === $user->id;
            });
    });

    it('logs missing_role reason on role failure', function () {
        Log::spy();

        $request = Request::create('/api/users', 'GET');
        $user = User::factory()->create(['is_active' => true]);

        // Simular usuario autenticado en el request
        $request->setUserResolver(fn () => $user);

        $handler = new HasRoleHandler('admin');
        $result = $handler->handle($request);

        expect($result->getStatusCode())->toBe(403);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($context['reason'], 'missing_role');
            });
    });
});
