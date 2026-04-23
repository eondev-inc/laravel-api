# Laravel API — Documentación técnica

API REST para un SaaS de venta de camisetas personalizadas. Construida con Laravel 13, PHP 8.4 y PostgreSQL. Implementa autenticación con Sanctum, un sistema RBAC propio (sin Spatie), el patrón **Chain of Responsibility** para autorización y autenticación, integración con **Transbank Webpay Plus** y documentación interactiva con **Scramble (OpenAPI/Swagger)**.

---

## Índice

1. [Arquitectura general](#arquitectura-general)
2. [Infraestructura Docker](#infraestructura-docker)
3. [Chain of Responsibility — Autorización (RBAC)](#chain-of-responsibility--autorización-rbac)
4. [Chain of Responsibility — Autenticación](#chain-of-responsibility--autenticación)
5. [RBAC — Roles y Permisos](#rbac--roles-y-permisos)
6. [Endpoints de la API](#endpoints-de-la-api)
7. [Catálogo](#catálogo)
8. [Carrito de compras](#carrito-de-compras)
9. [Checkout y Pagos (Transbank)](#checkout-y-pagos-transbank)
10. [Seguridad](#seguridad)
11. [Documentación interactiva (Swagger)](#documentación-interactiva-swagger)
12. [Datos de prueba (Seeders)](#datos-de-prueba-seeders)
13. [Testing](#testing)
14. [Configuración y Variables de Entorno](#configuración-y-variables-de-entorno)
15. [Decisiones de diseño y gotchas](#decisiones-de-diseño-y-gotchas)

---

## Arquitectura general

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────────────────────────┐
│  routes/api.php                                             │
│                                                             │
│  POST   /api/login                → AuthController (público)│
│  POST   /api/logout               → AuthController (sanctum)│
│  GET    /api/user                 → closure       (sanctum) │
│  apiResource /api/users           → UserController (sanctum)│
│  GET    /api/categories[/{id}]    → CategoryController      │
│  GET    /api/products[/{id}]      → ProductController       │
│  GET    /api/designs[/{id}]       → DesignController        │
│  GET|*  /api/cart                 → CartController          │
│  POST   /api/checkout             → CheckoutController      │
│  GET|POST /api/checkout/commit    → CheckoutController      │
└─────────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────────┐
│  Middleware: auth:sanctum (Sanctum bearer token)            │
│  Middleware: throttle:api (60 req/min por usuario o IP)     │
│  Middleware: SecurityHeaders (X-Frame-Options, HSTS, etc.)  │
└─────────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────────┐
│  Controller                                                 │
│  ├── AuthController     → Pipeline CoR Autenticación        │
│  ├── UserController     → Pipeline CoR Autorización         │
│  ├── CategoryController → Pipeline CoR Autorización         │
│  ├── ProductController  → Pipeline CoR Autorización         │
│  ├── DesignController   → Pipeline CoR Autorización         │
│  ├── CartController     → CartResolver (guest + auth)       │
│  └── CheckoutController → TransbankGateway (interface)      │
└─────────────────────────────────────────────────────────────┘
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
├── Contracts/
│   └── Payments/
│       └── TransbankGateway.php          ← interface del gateway de pago
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php                ← método authorize() base
│   │   ├── AuthController.php            ← login / logout
│   │   ├── UserController.php            ← CRUD usuarios
│   │   ├── CategoryController.php        ← CRUD categorías
│   │   ├── ProductController.php         ← CRUD productos
│   │   ├── DesignController.php          ← CRUD diseños + upload
│   │   ├── CartController.php            ← carrito guest + auth
│   │   └── CheckoutController.php        ← checkout + callback Transbank
│   ├── Middleware/
│   │   └── SecurityHeaders.php           ← headers de seguridad HTTP
│   ├── Requests/                         ← Form Requests con validación
│   └── Resources/                        ← API Resources (transformación JSON)
├── Models/
│   ├── User.php
│   ├── Role.php
│   ├── Permission.php
│   ├── Category.php
│   ├── Product.php
│   ├── ProductVariation.php
│   ├── Design.php
│   ├── Cart.php
│   ├── CartItem.php
│   ├── Order.php
│   └── OrderItem.php
└── Services/
    ├── CartResolver.php                  ← resuelve carrito guest o autenticado
    └── TransbankService.php              ← implementación del gateway Transbank
```

---

## Infraestructura Docker

### Servicios

El stack de desarrollo usa **Traefik v3** como proxy reverso. Todos los servicios web son accesibles por dominio, sin necesidad de recordar puertos.

| Servicio | Imagen | Dominio local | Puerto interno |
|---|---|---|---|
| `traefik` | `traefik:v3` | `http://localhost:8081` (dashboard) | 80 |
| `app` | `php:8.4-cli-alpine` (custom) | `http://api.laravel.localhost` | 8000 |
| `adminer` | `adminer:latest` | `http://adminer.laravel.localhost` | 8080 |
| `postgres` | `postgres:16-alpine` | — (solo interno) | 5432 |
| `redis` | `redis:7-alpine` | — (solo interno) | 6379 |

`postgres` y `redis` tienen healthchecks. El servicio `app` espera a que ambos estén healthy antes de arrancar (`depends_on: condition: service_healthy`).

### Comandos

```bash
# Levantar todo el stack
docker compose up -d

# Reconstruir la imagen de la app (tras cambios en Dockerfile o composer.json)
docker compose up -d --build app

# Correr migraciones dentro del contenedor
docker compose exec app php artisan migrate

# Correr seeders
docker compose exec app php artisan db:seed

# Resetear DB y re-sembrar
docker compose exec app php artisan migrate:fresh --seed

# Ver logs de un servicio
docker compose logs -f app
```

### Dockerfile

**`Dockerfile`** — imagen base `php:8.4-cli-alpine`. Instala en un solo layer para optimizar cache:

- Extensiones PHP: `pdo`, `pdo_pgsql`, `mbstring`, `zip`, `pcntl`
- PECL: `redis` (phpredis)
- Composer 2.7 copiado desde imagen oficial

El `composer install` se ejecuta antes de copiar el código fuente para aprovechar el cache de capas de Docker.

> **Importante:** El contenedor usa `php -S 0.0.0.0:8000 -t public/ public/index.php` directamente (no `php artisan serve`). Ver [gotcha sobre artisan serve](#artisan-serve-y-variables-de-entorno-en-docker).

### Base de datos de tests

**`docker/postgres/init.sql`** — se ejecuta automáticamente al inicializar el contenedor y crea la base de datos `laravel_api_test`. Requiere el flag `:z` en el volume (necesario en Fedora con SELinux).

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
$auth = $this->authorize($request, role: 'admin', permission: 'catalog.manage');
if ($auth !== true) return $auth;
```

### Agregar un nuevo handler de autorización

1. Crear la clase en `app/Auth/Chain/` extendiendo `AbstractHandler`.
2. Implementar `handle(Request $request): true|JsonResponse`.
3. Llamar `$this->passToNext($request)` cuando la validación pase.
4. Agregar el parámetro correspondiente en `Controller::authorize()` si aplica.

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

Busca el usuario por email. Si no existe, retorna 422 con mensaje genérico (evita enumerar usuarios). Si existe, adjunta el modelo `User` al request bajo la clave `_resolved_user`.

> Retorna 422 (no 401) intencionalmente para que `AuthController` no registre el intento en Redis cuando el email es desconocido.

#### `RateLimitHandler`

Verifica el contador de intentos fallidos en Redis. La key es `login-attempts:{email}`.

Configuración vía `.env`:

```dotenv
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
```

Respuesta al superar el límite:

```json
{
    "message": "Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.",
    "retry_after_seconds": 900
}
```

Con header HTTP: `Retry-After: 900`.

#### `PasswordMatchesHandler`

Verifica el password usando `password_verify()` nativo de PHP (no `Hash::check()`). Esto mantiene el handler independiente del IoC container, permitiendo testearlo en la suite Unit sin arrancar el framework.

#### `AccountActiveHandler`

Verifica la columna `is_active`. Se evalúa al final de la cadena para no revelar si una cuenta inactiva existe antes de confirmar que las credenciales son correctas.

---

## RBAC — Roles y Permisos

### Modelo de datos

```
users ──< role_user >── roles ──< permission_role >── permissions
```

Todas las tablas pivot tienen `CASCADE DELETE` en sus foreign keys.

### Modelos

Los tres modelos usan atributos PHP 8 nativos:

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
| `admin@example.com` | admin | `users.view`, `users.create`, `users.edit`, `users.delete`, `catalog.manage` |
| `editor@example.com` | editor | `users.view`, `users.edit` |
| `viewer@example.com` | viewer | `users.view` |

Contraseña de todos: `password`

---

## Endpoints de la API

### Autenticación

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| `POST` | `/api/login` | — | Login con email y password |
| `POST` | `/api/logout` | Bearer token | Revoca el token actual |
| `GET` | `/api/user` | Bearer token | Datos del usuario autenticado |

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
        "id": "uuid-...",
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

## Catálogo

### Modelo de datos

```
categories ──< products ──< product_variations
                    │
                    └──< designs (con file_path + file_url)
```

Todos los modelos exponen un `uuid` público. Los IDs internos nunca se exponen en la API.

### Endpoints de categorías

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| `GET` | `/api/categories` | — | Lista paginada de categorías activas |
| `GET` | `/api/categories/{uuid}` | — | Ver categoría |
| `POST` | `/api/categories` | admin + `catalog.manage` | Crear categoría |
| `PUT` | `/api/categories/{uuid}` | admin + `catalog.manage` | Actualizar categoría |
| `DELETE` | `/api/categories/{uuid}` | admin + `catalog.manage` | Eliminar categoría |

### Endpoints de productos

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| `GET` | `/api/products` | — | Lista paginada de productos activos (incluye categoría) |
| `GET` | `/api/products/{uuid}` | — | Ver producto con categoría |
| `POST` | `/api/products` | admin + `catalog.manage` | Crear producto |
| `PUT` | `/api/products/{uuid}` | admin + `catalog.manage` | Actualizar producto |
| `DELETE` | `/api/products/{uuid}` | admin + `catalog.manage` | Eliminar producto |

**Crear producto — request:**

```json
{
    "name": "Camiseta básica",
    "slug": "camiseta-basica",
    "description": "100% algodón",
    "price": 9990,
    "category_id": "uuid-de-la-categoria",
    "is_active": true
}
```

### Endpoints de diseños

Los diseños son imágenes subidas por usuarios autenticados y asociadas a un producto. El upload está limitado a 10 req/min (`throttle:uploads`).

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| `GET` | `/api/designs` | — | Lista paginada de diseños activos |
| `GET` | `/api/designs/{uuid}` | — | Ver diseño |
| `POST` | `/api/designs` | Bearer token (throttle:uploads) | Subir diseño (multipart/form-data) |
| `DELETE` | `/api/designs/{uuid}` | admin + `catalog.manage` | Eliminar diseño y archivo |

**Subir diseño — request (multipart/form-data):**

```
name        = "Mi diseño"
product_id  = "uuid-del-producto"
image       = [archivo: jpg, png, svg, webp — máx 5MB]
```

---

## Carrito de compras

### Estrategia de sesión

El carrito soporta dos modos simultáneos:

- **Guest:** Se identifica por el header `X-Cart-Session: {uuid}`. El UUID lo genera el cliente (frontend) y lo persiste localmente.
- **Autenticado:** Se identifica por el Bearer token. El carrito se asocia al `user_id`.

El servicio `CartResolver` (`app/Services/CartResolver.php`) decide qué carrito usar según los headers presentes en el request.

### Endpoints del carrito

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| `GET` | `/api/cart` | Guest o Bearer | Ver carrito con items |
| `POST` | `/api/cart/items` | Guest o Bearer | Agregar item al carrito |
| `PUT` | `/api/cart/items/{id}` | Guest o Bearer | Actualizar cantidad de un item |
| `DELETE` | `/api/cart/items/{id}` | Guest o Bearer | Eliminar item del carrito |
| `POST` | `/api/cart/merge` | Bearer | Fusionar carrito guest al carrito del usuario |

**Agregar item — request:**

```json
{
    "product_variation_id": "uuid-de-la-variacion",
    "design_id": "uuid-del-diseno",
    "quantity": 2
}
```

> `design_id` es opcional. Si se incluye, el `price_modifier` del diseño se suma al precio base de la variación.

**Merge — headers requeridos:**

```
Authorization: Bearer {token}
X-Cart-Session: {uuid-del-carrito-guest}
```

### Cálculo de precio unitario

```
unit_price = variation.price (o product.price si variation.price es null)
           + design.price_modifier (o 0 si no hay diseño)
```

El precio se calcula y persiste en el `CartItem` al momento de agregar. No se recalcula al hacer checkout.

---

## Checkout y Pagos (Transbank)

### Flujo completo

```
1. POST /api/checkout
   ├── Convierte el carrito activo en una Order (status: pending)
   ├── Llama a TransbankGateway::create()
   ├── Guarda token_ws y webpay_url en la Order
   └── Retorna { token_ws, url } al frontend

2. Frontend redirige al usuario a la URL de Webpay con el token_ws

3. Transbank redirige de vuelta a GET|POST /api/checkout/commit
   ├── Llama a TransbankGateway::commit(token_ws)
   ├── Actualiza Order.status = 'paid' o 'failed'
   └── Redirige al frontend a /checkout/success o /checkout/failure
```

### Endpoints

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| `POST` | `/api/checkout` | Bearer token | Iniciar checkout, crear orden y obtener URL de Webpay |
| `GET\|POST` | `/api/checkout/commit` | — (callback de Transbank) | Confirmar pago y actualizar estado de la orden |

**Iniciar checkout — respuesta (200):**

```json
{
    "token_ws": "01ab...",
    "url": "https://webpay3gint.transbank.cl/webpayserver/initTransaction"
}
```

### Entornos de Transbank

El gateway se configura automáticamente según `APP_ENV`:

| Entorno | Configuración |
|---|---|
| `local` / `staging` | Integración (credenciales de prueba hardcodeadas por Transbank) |
| `production` | Producción (requiere `TRANSBANK_API_KEY` y `TRANSBANK_COMMERCE_CODE` en `.env`) |

```dotenv
# Solo necesario en producción
TRANSBANK_API_KEY=tu_api_key
TRANSBANK_COMMERCE_CODE=tu_codigo_comercio
FRONTEND_URL=https://tu-frontend.com
```

### Interface del gateway

`app/Contracts/Payments/TransbankGateway.php` define el contrato. `TransbankService` es la implementación real. En tests se puede mockear la interface para no depender de la red de Transbank.

---

## Seguridad

### Headers HTTP

El middleware `SecurityHeaders` (`app/Http/Middleware/SecurityHeaders.php`) agrega los siguientes headers a todas las respuestas:

| Header | Valor |
|---|---|
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |

### CORS

Configurado en `config/cors.php`. El origen permitido se define con la variable de entorno `FRONTEND_URL`. En producción, solo el dominio del frontend puede hacer requests cross-origin.

### Rate Limiting

Configurado en `AppServiceProvider::boot()`:

| Limiter | Límite | Aplica a |
|---|---|---|
| `api` | 60 req/min por usuario o IP | Todas las rutas API |
| `uploads` | 10 req/min por usuario o IP | `POST /api/designs` |

### Prevención de fugas de datos

La suite `SecurityLeakTest` verifica que ningún endpoint exponga:
- IDs autoincrementales (`"id": 123`)
- Hashes de contraseñas (`$2y$10$...`)

---

## Documentación interactiva (Swagger)

La documentación OpenAPI se genera automáticamente con **Scramble** (`dedoc/scramble`). Lee los controllers, Form Requests y API Resources directamente, sin necesidad de anotaciones PHPDoc.

### Acceso

```
http://api.laravel.localhost/docs/api
```

> Solo accesible cuando `APP_ENV=local`. En cualquier otro entorno, Scramble retorna 403 gracias al Gate `viewApiDocs` definido en `AppServiceProvider`.

### Exportar especificación OpenAPI

```bash
php artisan scramble:export
# Genera api.json en la raíz del proyecto
```

### Colección de Postman

El archivo `postman_collection.json` en la raíz del proyecto contiene todos los endpoints listos para importar en Postman. Se generó a partir del `api.json` exportado por Scramble.

Para regenerar la colección tras agregar nuevos endpoints:

```bash
php artisan scramble:export
npx -y openapi-to-postmanv2 -s api.json -o postman_collection.json -p
```

---

## Datos de prueba (Seeders)

### Ejecutar seeders

```bash
# Solo seeders (sin borrar datos existentes)
docker compose exec app php artisan db:seed

# Resetear DB completa y re-sembrar
docker compose exec app php artisan migrate:fresh --seed
```

### Qué genera cada seeder

| Seeder | Datos generados |
|---|---|
| `RoleSeeder` | Roles: `admin`, `editor`, `viewer` |
| `PermissionSeeder` | Permisos RBAC + `catalog.manage`. Los asigna a los roles correspondientes. |
| `UserSeeder` | 3 usuarios de prueba (ver tabla en sección RBAC) |
| `DatabaseSeeder` (factories) | 5 categorías, 10 productos por categoría, 3 variaciones por producto, 20 diseños |

### Usuarios de prueba

| Email | Contraseña | Rol |
|---|---|---|
| `admin@example.com` | `password` | admin |
| `editor@example.com` | `password` | editor |
| `viewer@example.com` | `password` | viewer |

---

## Testing

### Estrategia

| Suite | Ubicación | Usa DB | Usa IoC container |
|---|---|---|---|
| Unit | `tests/Unit/` | No | No (mocks) |
| Feature | `tests/Feature/` | Sí (`laravel_api_test`) | Sí |

`RefreshDatabase` está activado globalmente en `tests/Pest.php` para todos los tests de Feature.

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

# Con detalle
./vendor/bin/pest --verbose
```

### Variables de entorno en tests (`phpunit.xml`)

```xml
<env name="DB_DATABASE" value="laravel_api_test"/>
<env name="BCRYPT_ROUNDS" value="4"/>
```

`BCRYPT_ROUNDS=4` reduce el tiempo de hashing en tests sin afectar seguridad en producción.

---

## Configuración y Variables de Entorno

```dotenv
# Aplicación
APP_ENV=local
APP_DEBUG=true
FRONTEND_URL=http://localhost:3000

# Base de datos
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1      # En Docker, el contenedor usa DB_HOST=postgres (inyectado por docker-compose)
DB_PORT=5432
DB_DATABASE=laravel_api
DB_USERNAME=laravel
DB_PASSWORD=secret

# Redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1   # En Docker, el contenedor usa REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null

# Rate limiting de login
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15

# Transbank (solo producción)
TRANSBANK_API_KEY=
TRANSBANK_COMMERCE_CODE=

# Sanctum (tokens sin expiración por defecto)
# Para activar expiración: 'expiration' => 60 * 24 en config/sanctum.php
```

---

## Decisiones de diseño y gotchas

### Por qué Chain of Responsibility y no Gate/Policy

El patrón CoR permite construir pipelines de validación composables y testables de forma aislada. Cada handler tiene una única responsabilidad y puede probarse con un mock de `Request` sin arrancar el framework. Gate y Policy de Laravel acoplan la lógica de autorización al sistema de autenticación del framework.

### `_resolved_user` en el request

`EmailExistsHandler` adjunta el modelo `User` al request con `$request->merge(['_resolved_user' => $user])`. Esto evita que `PasswordMatchesHandler` y `AccountActiveHandler` hagan una segunda consulta a la base de datos. Es una convención interna de la cadena de autenticación — no es un campo del request HTTP.

### `password_verify()` en lugar de `Hash::check()`

`PasswordMatchesHandler` usa la función nativa de PHP para mantener independencia del IoC container. `Hash::check()` requiere que el container esté inicializado, lo que impide testear el handler en la suite Unit con mocks simples.

### Orden de `AccountActiveHandler` al final

Verificar si la cuenta está activa al final (después de validar credenciales) evita revelar la existencia de cuentas inactivas. Un atacante que recibe 403 sabe que las credenciales son correctas, pero eso es preferible a revelar si el email existe.

### Rate limiting solo cuando el email existe

`AuthController` no llama a `$rateLimiter->hit()` cuando `EmailExistsHandler` retorna 422. Registrar intentos para emails inexistentes permitiría a un atacante bloquear cuentas sin conocer la contraseña (ataque de denegación de servicio).

### UUIDs públicos en lugar de IDs autoincrementales

Todos los modelos del catálogo y usuarios exponen un `uuid` en la API. Los IDs internos de PostgreSQL nunca salen en las respuestas JSON. Esto previene enumeración de recursos y es verificado automáticamente por `SecurityLeakTest`.

### `artisan serve` y variables de entorno en Docker

Laravel 11 `ServeCommand` limpia intencionalmente todas las variables de entorno del sistema antes de lanzar el servidor interno, para que el archivo `.env` tenga prioridad absoluta. Esto hace que las variables inyectadas por Docker (`DB_HOST=postgres`, `REDIS_HOST=redis`) sean descartadas, causando que la app intente conectarse a `127.0.0.1` desde dentro del contenedor.

**Solución:** Usar el servidor nativo de PHP directamente:

```yaml
# docker-compose.yml
command: ["php", "-S", "0.0.0.0:8000", "-t", "public/", "public/index.php"]
```

### Traefik y SELinux en Fedora

El contenedor de Traefik necesita acceso al socket de Docker. En Fedora con SELinux activo, el mount del socket requiere deshabilitar el label de SELinux para ese servicio:

```yaml
volumes:
  - "/var/run/docker.sock:/var/run/docker.sock:ro,z"
security_opt:
  - label:disable
```

Además, usar `traefik:v3` (no `traefik:v3.0`) garantiza que se use una versión del cliente Docker interno compatible con Docker Engine 29+.

### SELinux y bind mounts en Fedora

Todos los bind mounts de archivos del host requieren el flag `:z`:

```yaml
volumes:
  - .:/var/www/html:z
  - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql:z
```

### `pdo_pgsql` en Fedora (desarrollo local sin Docker)

```bash
sudo dnf install -y php-pgsql
```

Verificar instalación: `php -m | grep pdo_pgsql`
