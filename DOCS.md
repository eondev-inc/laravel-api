# Laravel API — Documentación técnica

POC de una API REST construida con Laravel 13, PHP 8.4 y PostgreSQL. Implementa autenticación con Sanctum, un sistema RBAC propio (sin Spatie) y el patrón **Chain of Responsibility** en dos pipelines independientes: uno para autorización (RBAC) y otro para autenticación (login).

---

## Arquitectura general

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────────────────┐
│  routes/api.php                                     │
│                                                     │
│  POST /api/login          → AuthController (público)│
│  POST /api/logout         → AuthController (sanctum)│
│  GET  /api/user           → closure       (sanctum) │
│  GET|POST|PUT|DELETE      → UserController (sanctum)│
│    /api/users[/{user}]                              │
└─────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────┐
│  Middleware: auth:sanctum (Sanctum bearer token)    │
└─────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────┐
│  Controller                                         │
│  ├── AuthController  → Pipeline CoR Autenticación   │
│  └── UserController  → Pipeline CoR Autorización    │
└─────────────────────────────────────────────────────┘
```

### Estructura de directorios relevante

```
app/
├── Auth/
│   └── Chain/
│       ├── Contracts/
│       │   └── AuthorizationHandler.php   ← interface compartida
│       ├── AbstractHandler.php            ← mecanismo de encadenamiento
│       ├── AuthenticatedHandler.php       ← verifica user != null
│       ├── HasRoleHandler.php             ← verifica rol RBAC
│       ├── HasPermissionHandler.php       ← verifica permiso RBAC
│       └── Authentication/
│           ├── EmailExistsHandler.php     ← email existe en DB
│           ├── RateLimitHandler.php       ← rate limiting Redis
│           ├── PasswordMatchesHandler.php ← verifica password
│           └── AccountActiveHandler.php   ← cuenta activa
├── Http/Controllers/
│   ├── Controller.php                     ← método authorize() base
│   ├── AuthController.php                 ← login / logout
│   └── UserController.php                 ← CRUD usuarios
└── Models/
    ├── User.php
    ├── Role.php
    └── Permission.php
```

---

## Chain of Responsibility — Autorización (RBAC)

### Propósito

Verificar que el usuario autenticado tiene el rol y/o permiso requerido para ejecutar una acción. Se invoca desde los controllers mediante `$this->authorize()`. No usa `Gate` ni `Policy` de Laravel.

### Pipeline

```
Request
   │
   ▼
AuthenticatedHandler  ──── user === null? ──→ 401 Unauthenticated
   │ pasa
   ▼
HasRoleHandler (opcional) ── !hasRole? ──→ 403 Forbidden
   │ pasa
   ▼
HasPermissionHandler (opcional) ── !hasPermission? ──→ 403 Forbidden
   │ pasa
   ▼
 true
```

`HasRoleHandler` y `HasPermissionHandler` son opcionales: el controller base los agrega a la cadena solo si se pasan los parámetros correspondientes.

### Interface y clase base

**`app/Auth/Chain/Contracts/AuthorizationHandler.php`**

```php
interface AuthorizationHandler
{
    public function setNext(self $handler): self;
    public function handle(Request $request): true|JsonResponse;
}
```

**`app/Auth/Chain/AbstractHandler.php`**

Implementa `setNext()` y expone `passToNext()` para los handlers concretos. Cuando no hay siguiente handler, `passToNext()` retorna `true` (cadena completa aprobada).

```php
abstract class AbstractHandler implements AuthorizationHandler
{
    private ?AuthorizationHandler $next = null;

    public function setNext(AuthorizationHandler $handler): AuthorizationHandler
    {
        $this->next = $handler;
        return $handler; // permite encadenamiento fluido
    }

    protected function passToNext(Request $request): true|JsonResponse
    {
        if ($this->next === null) {
            return true;
        }
        return $this->next->handle($request);
    }
}
```

### Handlers de autorización

| Handler | Archivo | Falla con | Condición de fallo |
|---|---|---|---|
| `AuthenticatedHandler` | `app/Auth/Chain/AuthenticatedHandler.php` | 401 | `$request->user() === null` |
| `HasRoleHandler` | `app/Auth/Chain/HasRoleHandler.php` | 403 | `!$user->hasRole($role)` |
| `HasPermissionHandler` | `app/Auth/Chain/HasPermissionHandler.php` | 403 | `!$user->hasPermission($permission)` |

### Construcción del pipeline en el Controller base

**`app/Http/Controllers/Controller.php`**

```php
protected function authorize(
    Request $request,
    string $role = '',
    string $permission = '',
): true|JsonResponse {
    $head = new AuthenticatedHandler;
    $tail = $head;

    if ($role !== '') {
        $tail = $tail->setNext(new HasRoleHandler($role));
    }

    if ($permission !== '') {
        $tail->setNext(new HasPermissionHandler($permission));
    }

    return $head->handle($request);
}
```

### Uso en controllers

```php
// Solo verificar autenticación
$auth = $this->authorize($request);
if ($auth !== true) return $auth;

// Verificar rol
$auth = $this->authorize($request, role: 'admin');
if ($auth !== true) return $auth;

// Verificar rol + permiso (ambos requeridos)
$auth = $this->authorize($request, role: 'admin', permission: 'users.delete');
if ($auth !== true) return $auth;
```

Ejemplo real en `UserController::destroy()`:

```php
public function destroy(Request $request, User $user): JsonResponse
{
    $auth = $this->authorize($request, role: 'admin', permission: 'users.delete');
    if ($auth !== true) {
        return $auth;
    }

    $user->delete();

    return response()->json(['message' => 'User deleted.'], 200);
}
```

### Agregar un nuevo handler de autorización

1. Crear la clase en `app/Auth/Chain/` extendiendo `AbstractHandler`.
2. Implementar `handle(Request $request): true|JsonResponse`.
3. Llamar `$this->passToNext($request)` cuando la validación pase.
4. Agregar el parámetro correspondiente en `Controller::authorize()` si aplica.

```php
class HasScopeHandler extends AbstractHandler
{
    public function __construct(private readonly string $scope) {}

    public function handle(Request $request): true|JsonResponse
    {
        if (! $request->user()->hasScope($this->scope)) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        return $this->passToNext($request);
    }
}
```

---

## Chain of Responsibility — Autenticación

### Propósito

Validar las credenciales de login en pasos atómicos e independientes, con rate limiting integrado. Se construye y ejecuta directamente en `AuthController::login()`.

### Pipeline

```
Request (email + password)
   │
   ▼
EmailExistsHandler ── email no existe en DB? ──→ 422 (no registra intento)
   │ pasa — adjunta _resolved_user al request
   ▼
RateLimitHandler ── demasiados intentos? ──→ 429 + Retry-After header
   │ pasa
   ▼
PasswordMatchesHandler ── password incorrecto? ──→ 401 (registra intento)
   │ pasa
   ▼
AccountActiveHandler ── is_active === false? ──→ 403 (registra intento)
   │ pasa
   ▼
 true → emitir token Sanctum + limpiar contador Redis
```

### Handlers de autenticación

#### `EmailExistsHandler`

**`app/Auth/Chain/Authentication/EmailExistsHandler.php`**

Busca el usuario por email en la base de datos. Si no existe, retorna 422 con un mensaje genérico (evita enumerar usuarios). Si existe, adjunta el modelo `User` al request bajo la clave `_resolved_user` para que los handlers siguientes lo usen sin repetir la consulta.

```php
$request->merge(['_resolved_user' => $user]);
```

> **Nota:** Retorna 422 (no 401) intencionalmente. Esto permite al `AuthController` distinguir "email no existe" de "password incorrecto" y no registrar el intento en Redis cuando el email es desconocido.

#### `RateLimitHandler`

**`app/Auth/Chain/Authentication/RateLimitHandler.php`**

Verifica el contador de intentos fallidos en Redis. La key es `login-attempts:{email}`.

Configuración vía `.env`:

```
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
```

Expone dos métodos públicos que el `AuthController` usa directamente:

```php
$rateLimiter->hit($request);   // registra un intento fallido
$rateLimiter->clear($request); // limpia el contador al hacer login exitoso
```

Respuesta al superar el límite:

```json
{
    "message": "Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.)",
    "retry_after_seconds": 900
}
```

Con header HTTP: `Retry-After: 900`.

#### `PasswordMatchesHandler`

**`app/Auth/Chain/Authentication/PasswordMatchesHandler.php`**

Verifica el password usando `password_verify()` nativo de PHP en lugar del facade `Hash`. Esto mantiene el handler independiente del IoC container de Laravel, lo que permite testearlo en la suite Unit sin necesidad de arrancar el framework.

Lee el usuario desde `$request->get('_resolved_user')` (adjuntado por `EmailExistsHandler`).

#### `AccountActiveHandler`

**`app/Auth/Chain/Authentication/AccountActiveHandler.php`**

Verifica la columna `is_active` del usuario. Se evalúa al final de la cadena, después de confirmar que las credenciales son correctas, para no revelar si una cuenta inactiva existe a través del mensaje de error.

### Construcción del pipeline en AuthController

**`app/Http/Controllers/AuthController.php`**

```php
public function login(Request $request): JsonResponse
{
    $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $rateLimiter = new RateLimitHandler;

    $emailHandler = new EmailExistsHandler;
    $emailHandler
        ->setNext($rateLimiter)
        ->setNext(new PasswordMatchesHandler)
        ->setNext(new AccountActiveHandler);

    $result = $emailHandler->handle($request);

    if ($result !== true) {
        // No registrar intento si el email no existe (422)
        if ($result->getStatusCode() !== 422) {
            $rateLimiter->hit($request);
        }
        return $result;
    }

    $rateLimiter->clear($request);

    $user = $request->get('_resolved_user');
    $token = $user->createToken('api-token')->plainTextToken;

    return new JsonResponse([
        'token' => $token,
        'token_type' => 'Bearer',
        'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
    ], 200);
}
```

---

## RBAC — Roles y Permisos

### Modelo de datos

```
users ──< role_user >── roles ──< permission_role >── permissions
```

Todas las tablas pivot tienen `CASCADE DELETE` en sus foreign keys.

### Modelos

Los tres modelos usan atributos PHP 8 nativos en lugar de arrays:

```php
#[Fillable(['name', 'email', 'password', 'is_active'])]
class User extends Authenticatable { ... }
```

**`User`** — métodos RBAC:

```php
public function hasRole(string $role): bool
{
    return $this->roles()->where('name', $role)->exists();
}

public function hasPermission(string $permission): bool
{
    return $this->roles()
        ->whereHas('permissions', fn ($q) => $q->where('permissions.name', $permission))
        ->exists();
}
```

> **Consideración de rendimiento:** `hasPermission()` hace un `whereHas` encadenado. Si un usuario tiene muchos roles, considerar eager loading con `$user->load('roles.permissions')` antes de llamar al pipeline.

### Seeders

Orden de ejecución: `RoleSeeder → PermissionSeeder → UserSeeder`

| Usuario | Rol | Permisos |
|---|---|---|
| `admin@example.com` | admin | `users.view`, `users.create`, `users.edit`, `users.delete` |
| `editor@example.com` | editor | `users.view`, `users.edit` |
| `viewer@example.com` | viewer | `users.view` |

Contraseña de todos: `password`

```bash
php artisan db:seed
```

---

## Endpoints de la API

### Autenticación

| Método | Ruta | Middleware | Descripción |
|---|---|---|---|
| `POST` | `/api/login` | — | Login con email y password |
| `POST` | `/api/logout` | `auth:sanctum` | Revoca el token actual |
| `GET` | `/api/user` | `auth:sanctum` | Datos del usuario autenticado |

**Login — request:**

```json
{
    "email": "admin@example.com",
    "password": "password"
}
```

**Login — respuesta exitosa (200):**

```json
{
    "token": "1|abc123...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "Admin",
        "email": "admin@example.com"
    }
}
```

### Gestión de usuarios

Todas las rutas requieren `Authorization: Bearer {token}` y rol `admin`.

| Método | Ruta | Permiso adicional | Descripción |
|---|---|---|---|
| `GET` | `/api/users` | — | Lista paginada (15/página) |
| `POST` | `/api/users` | — | Crear usuario |
| `GET` | `/api/users/{id}` | — | Ver usuario |
| `PUT` | `/api/users/{id}` | — | Actualizar usuario |
| `DELETE` | `/api/users/{id}` | `users.delete` | Eliminar usuario |

---

## Infraestructura Docker

### Servicios

```yaml
# docker-compose.yml
services:
  postgres:   # postgres:16-alpine — puerto 5432
  redis:      # redis:7-alpine — puerto 6379, appendonly
  app:        # php:8.4-cli-alpine — puerto 8000
  adminer:    # adminer:latest — puerto 8080
```

`postgres` y `redis` tienen healthchecks. El servicio `app` espera a que ambos estén healthy antes de arrancar (`depends_on: condition: service_healthy`).

### Dockerfile

**`Dockerfile`** — imagen base `php:8.4-cli-alpine`. Instala en un solo layer para optimizar cache:

- Extensiones PHP: `pdo`, `pdo_pgsql`, `mbstring`, `zip`, `pcntl`
- PECL: `redis` (phpredis)
- Composer 2.7 copiado desde imagen oficial

El `composer install` se ejecuta antes de copiar el código fuente para aprovechar el cache de capas de Docker.

### Base de datos de tests

**`docker/postgres/init.sql`** — se ejecuta automáticamente al inicializar el contenedor y crea la base de datos `laravel_api_test`. Requiere permisos `644` y el flag `:z` en el volume (necesario en Fedora con SELinux).

---

## Configuración y Variables de Entorno

### Variables principales

```dotenv
# Base de datos
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_api
DB_USERNAME=laravel
DB_PASSWORD=secret

# Redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Rate limiting de login
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
```

### `config/auth.php`

```php
'login_max_attempts' => env('LOGIN_MAX_ATTEMPTS', 5),
'login_lockout_minutes' => env('LOGIN_LOCKOUT_MINUTES', 15),
```

### Sanctum

Los tokens no expiran por defecto (`expiration: null` en `config/sanctum.php`). Para activar expiración:

```php
// config/sanctum.php
'expiration' => 60 * 24, // 24 horas en minutos
```

---

## Testing

### Estrategia

| Suite | Ubicación | Usa DB | Usa IoC container |
|---|---|---|---|
| Unit | `tests/Unit/` | No | No (mocks) |
| Feature | `tests/Feature/` | Sí (`laravel_api_test`) | Sí |

`RefreshDatabase` está activado globalmente en `tests/Pest.php` para todos los tests de Feature.

### Regla de ubicación de handlers

Los handlers que tocan Eloquent (`User::where()`) o que dependen de Facades a través de modelos deben ir en la suite **Feature**, no Unit. Esto incluye:

- `EmailExistsHandler` — hace `User::where()`
- `PasswordMatchesHandler` — accede a `$user->password` (cast `hashed` del modelo)

Los handlers que solo usan lógica pura o Facades directamente (como `RateLimiter`) pueden testearse en Unit con `CACHE_STORE=array`.

### Autenticación en tests de Feature

```php
use Laravel\Sanctum\Sanctum;

// Correcto
Sanctum::actingAs($user);

// Incorrecto — no usar
$this->actingAs($user);
```

### Comandos

```bash
# Todos los tests
composer run test

# Un archivo específico
./vendor/bin/pest tests/Feature/AuthenticationTest.php

# Con cobertura de detalle
./vendor/bin/pest --verbose
```

### Variables de entorno en tests (`phpunit.xml`)

```xml
<env name="DB_DATABASE" value="laravel_api_test"/>
<env name="BCRYPT_ROUNDS" value="4"/>
```

`BCRYPT_ROUNDS=4` reduce el tiempo de hashing en tests sin afectar seguridad en producción.

---

## Decisiones de diseño y gotchas

### Por qué Chain of Responsibility y no Gate/Policy

El patrón CoR permite construir pipelines de validación composables y testables de forma aislada. Cada handler tiene una única responsabilidad y puede probarse con un mock de `Request` sin arrancar el framework. Gate y Policy de Laravel acoplan la lógica de autorización al sistema de autenticación del framework.

### `_resolved_user` en el request

`EmailExistsHandler` adjunta el modelo `User` al request con `$request->merge(['_resolved_user' => $user])`. Esto evita que `PasswordMatchesHandler` y `AccountActiveHandler` hagan una segunda consulta a la base de datos. Es una convención interna de la cadena de autenticación — no es un campo del request HTTP.

### `password_verify()` en lugar de `Hash::check()`

`PasswordMatchesHandler` usa la función nativa de PHP para mantener independencia del IoC container. `Hash::check()` requiere que el container esté inicializado, lo que impide testear el handler en la suite Unit con mocks simples.

### Orden de `AccountActiveHandler` al final

Verificar si la cuenta está activa al final (después de validar credenciales) evita revelar la existencia de cuentas inactivas a través del mensaje de error. Un atacante que recibe 403 sabe que las credenciales son correctas.

### Rate limiting solo cuando el email existe

`AuthController` no llama a `$rateLimiter->hit()` cuando `EmailExistsHandler` retorna 422. Registrar intentos para emails inexistentes permitiría a un atacante bloquear cuentas sin conocer la contraseña correcta (ataque de denegación de servicio).

### SELinux y Docker en Fedora

Los bind mounts de Docker requieren el flag `:z` en Fedora con SELinux activo:

```yaml
volumes:
  - .:/var/www/html:z
  - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql:z
```

Sin `:z`, el contenedor no puede leer los archivos del host.

### `pdo_pgsql` en Fedora (desarrollo local sin Docker)

```bash
sudo dnf install -y php-pgsql
```

Verificar instalación: `php -m | grep pdo_pgsql`
