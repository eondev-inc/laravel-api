<?php

use App\Auth\Chain\AbstractHandler;
use App\Auth\Chain\Authentication\CredentialsValidationHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Feature porque CredentialsValidationHandler consulta la base de datos.
// RefreshDatabase activo globalmente en tests/Pest.php.

describe('CredentialsValidationHandler', function () {
    it('calls the hash verifier with dummy hash when user does not exist (timing attack prevention)', function () {
        $verifierCallCount = 0;
        $verifierPassword = null;

        $spyVerifier = function (string $password, string $hash) use (&$verifierCallCount, &$verifierPassword): bool {
            $verifierCallCount++;
            $verifierPassword = $password;

            return false;
        };

        $request = Request::create('/', 'POST', [
            'email' => 'nonexistent@example.com',
            'password' => 'any-password',
        ]);

        $handler = new CredentialsValidationHandler($spyVerifier);
        $result = $handler->handle($request);

        expect($result->getStatusCode())->toBe(401);
        expect($verifierCallCount)->toBe(1); // dummy hash MUST be called
        expect($verifierPassword)->toBe(str_repeat('a', 10)); // correct dummy input
    });

    it('calls the hash verifier with real password when user exists', function () {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ]);

        $verifierCallCount = 0;
        $verifierPassword = null;

        $spyVerifier = function (string $password, string $hash) use (&$verifierCallCount, &$verifierPassword): bool {
            $verifierCallCount++;
            $verifierPassword = $password;

            return false; // force wrong password
        };

        $request = Request::create('/', 'POST', [
            'email' => 'user@example.com',
            'password' => 'any-password',
        ]);

        $handler = new CredentialsValidationHandler($spyVerifier);
        $result = $handler->handle($request);

        expect($result->getStatusCode())->toBe(401);
        expect($verifierCallCount)->toBe(1);
        expect($verifierPassword)->toBe('any-password'); // real password passed
    });

    it('returns 401 when email does not exist', function () {
        $request = Request::create('/', 'POST', [
            'email' => 'ghost@example.com',
            'password' => 'any-password',
        ]);

        $handler = new CredentialsValidationHandler;
        $result = $handler->handle($request);

        expect($result)->not->toBeTrue();
        expect($result->getStatusCode())->toBe(401);
        expect(json_decode($result->getContent(), true)['message'])
            ->toBe('Las credenciales proporcionadas son incorrectas.');
    });

    it('returns 401 when email exists but password is wrong', function () {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ]);

        $request = Request::create('/', 'POST', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $handler = new CredentialsValidationHandler;
        $result = $handler->handle($request);

        expect($result)->not->toBeTrue();
        expect($result->getStatusCode())->toBe(401);
        expect(json_decode($result->getContent(), true)['message'])
            ->toBe('Las credenciales proporcionadas son incorrectas.');
    });

    it('attaches resolved user and passes when email and password are correct', function () {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ]);

        $request = Request::create('/', 'POST', [
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ]);

        $handler = new CredentialsValidationHandler;
        $result = $handler->handle($request);

        expect($result)->toBeTrue();
        expect($request->get('_resolved_user'))->not->toBeNull();
        expect($request->get('_resolved_user')->id)->toBe($user->id);
    });

    it('delegates to next handler on valid credentials', function () {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ]);

        $request = Request::create('/', 'POST', [
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ]);

        $nextCalled = false;
        $next = new class($nextCalled) extends AbstractHandler
        {
            public function __construct(private bool &$called) {}

            public function handle(Request $request): true|JsonResponse
            {
                $this->called = true;

                return true;
            }
        };

        $handler = new CredentialsValidationHandler;
        $handler->setNext($next);
        $result = $handler->handle($request);

        expect($result)->toBeTrue();
        expect($nextCalled)->toBeTrue();
    });

    it('returns same generic 401 message regardless of whether email exists (no user enumeration)', function () {
        // Email no existe
        $requestNoUser = Request::create('/', 'POST', [
            'email' => 'nonexistent@example.com',
            'password' => 'any-password',
        ]);
        $handler = new CredentialsValidationHandler;
        $resultNoUser = $handler->handle($requestNoUser);

        // Email existe pero contraseña incorrecta
        User::factory()->create([
            'email' => 'exists@example.com',
            'password' => 'correct-password',
        ]);
        $requestWrongPass = Request::create('/', 'POST', [
            'email' => 'exists@example.com',
            'password' => 'wrong-password',
        ]);
        $handler2 = new CredentialsValidationHandler;
        $resultWrongPass = $handler2->handle($requestWrongPass);

        // Ambas respuestas deben ser 401 con el mismo mensaje
        expect($resultNoUser->getStatusCode())->toBe(401);
        expect($resultWrongPass->getStatusCode())->toBe(401);
        expect(json_decode($resultNoUser->getContent(), true)['message'])
            ->toBe(json_decode($resultWrongPass->getContent(), true)['message']);
    });
});
