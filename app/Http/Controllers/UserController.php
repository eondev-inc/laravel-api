<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * GET /api/users
     * Lista todos los usuarios. Requiere rol admin OR permiso users.view.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $auth = $this->authorize($request, role: ['admin'], permission: ['users.view']);
        if ($auth !== true) {
            return $auth;
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $users = User::with('roles')->paginate($perPage);

        return UserResource::collection($users);
    }

    /**
     * POST /api/users
     * Crea un nuevo usuario. Requiere rol admin OR permiso users.create.
     */
    public function store(StoreUserRequest $request): UserResource|JsonResponse
    {
        $auth = $this->authorize($request, role: ['admin'], permission: ['users.create']);
        if ($auth !== true) {
            return $auth;
        }

        $user = User::create($request->only('name', 'email', 'password'));

        if ($request->filled('roles')) {
            $roleIds = Role::whereIn('name', $request->roles)->pluck('id');
            $user->roles()->sync($roleIds);
        }

        return (new UserResource($user->load('roles')))->response()->setStatusCode(201);
    }

    /**
     * GET /api/users/{user}
     * Muestra un usuario. Requiere rol admin OR permiso users.view.
     */
    public function show(Request $request, User $user): UserResource|JsonResponse
    {
        $auth = $this->authorize($request, role: ['admin'], permission: ['users.view']);
        if ($auth !== true) {
            return $auth;
        }

        return new UserResource($user->load('roles'));
    }

    /**
     * PUT /api/users/{user}
     * Actualiza un usuario. Requiere rol admin OR permiso users.update.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource|JsonResponse
    {
        $auth = $this->authorize($request, role: ['admin'], permission: ['users.update']);
        if ($auth !== true) {
            return $auth;
        }

        $user->update($request->only('name', 'email', 'password'));

        if ($request->filled('roles')) {
            $roleIds = Role::whereIn('name', $request->roles)->pluck('id');
            $user->roles()->sync($roleIds);
        }

        return new UserResource($user->load('roles'));
    }

    /**
     * DELETE /api/users/{user}
     * Elimina un usuario. Requiere rol admin OR permiso users.delete.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $auth = $this->authorize($request, role: ['admin'], permission: ['users.delete']);
        if ($auth !== true) {
            return $auth;
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.'], 200);
    }
}
