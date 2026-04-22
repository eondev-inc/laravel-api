# AGENTS.md

Laravel 13 API con Sanctum + RBAC propio + Chain of Responsibility para autorización. PHP 8.4, PostgreSQL.

## Comandos

```bash
docker compose up -d       # levanta PostgreSQL (laravel_api) + DB de tests (laravel_api_test) + Adminer :8080
composer run dev           # PHP dev server + queue + logs + vite en paralelo (concurrently)
composer run test          # limpia config cache, luego corre los tests contra laravel_api_test
./vendor/bin/pint          # linting (Laravel Pint)
php artisan migrate        # corre migraciones contra laravel_api
php artisan db:seed        # RoleSeeder → PermissionSeeder → UserSeeder
```

Un test específico: `./vendor/bin/pest tests/Feature/UserManagementTest.php`

**Orden recomendado antes de commit**: `pint → test`

**Prerequisito del sistema**: `pdo_pgsql` debe estar instalado (`php -m | grep pdo_pgsql`).
En Fedora: `sudo dnf install -y php-pgsql`. En Fedora con SELinux, los bind mounts de Docker requieren `:z`.

## Arquitectura

- **Auth**: Laravel Sanctum 4 — bearer tokens + SPA stateful cookies. Guard: `auth:sanctum`.
- **Autorización**: Chain of Responsibility en `app/Auth/Chain/`. Los controllers llaman a `$this->authorize($request, role: 'admin', permission: 'users.delete')` — nunca usar `Gate` ni `Policy`.
- **Rutas API**: `auth:sanctum` como middleware de grupo. `apiResource('users', ...)` + `/api/user`. Sin versionado.
- **Modelos**: `User`, `Role`, `Permission`. Todos usan `#[Fillable]` (atributo PHP 8 nativo, no array) — patrón Laravel 13.
- **RBAC**: `users` ↔ `roles` (pivot `role_user`) ↔ `permissions` (pivot `permission_role`). Sin Spatie.
- **Health check**: `GET /up` — registrado automáticamente en `bootstrap/app.php`.

## Chain of Responsibility — cómo funciona

```
app/Auth/Chain/
├── Contracts/AuthorizationHandler.php  ← interface
├── AbstractHandler.php                 ← implementa setNext() + passToNext()
├── AuthenticatedHandler.php            ← ¿user !== null? → 401 si falla
├── HasRoleHandler.php                  ← ¿user->hasRole($role)? → 403 si falla
└── HasPermissionHandler.php            ← ¿user->hasPermission($perm)? → 403 si falla
```

El `Controller` base construye la cadena según los parámetros recibidos. Para agregar un nuevo handler: extender `AbstractHandler`, implementar `handle()`, y llamar `$this->passToNext($request)` cuando pase.

## Base de datos

- **Driver**: PostgreSQL. Contenedor Docker: host `127.0.0.1`, puerto `5432`, user `laravel`, password `secret`.
- **DB desarrollo**: `laravel_api`. **DB tests**: `laravel_api_test` (creada por `docker/postgres/init.sql` al inicializar el contenedor).
- **`RefreshDatabase` activo globalmente** en `tests/Pest.php` — aplica a todos los tests de `Feature/`.
- **Migraciones RBAC**: `roles`, `permissions`, `role_user`, `permission_role` con FK y cascade delete.
- **Seeders de prueba**: `admin@example.com` (admin), `editor@example.com` (editor), `viewer@example.com` (viewer). Contraseña: `password`.

## Tests

- Framework: **Pest v4**. Suites: `Unit` (sin DB) y `Feature` (con `RefreshDatabase` contra `laravel_api_test`).
- Tests unitarios del CoR usan mocks de `User` y `Request` — no tocan DB.
- Variables de test en `phpunit.xml`: `BCRYPT_ROUNDS=4`, `DB_DATABASE=laravel_api_test`.
- Usar `Sanctum::actingAs($user)` para autenticar en tests de feature (no `$this->actingAs`).

## Quirks

- `composer run dev` usa `concurrently` — matar uno mata todos.
- Sanctum tokens no expiran por defecto (`expiration: null` en `config/sanctum.php`).
- `AppServiceProvider` vacío — registrar bindings y observers ahí.
- `MustVerifyEmail` comentado en `User.php` — verificación de email no activa.
- Vite + Tailwind CSS 4 presentes en el skeleton pero este es un API backend puro.
- `hasPermission()` en `User` hace `whereHas` encadenado — si el usuario tiene muchos roles, considerar eager loading en producción.

