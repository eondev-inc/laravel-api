<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'users.view',    'display_name' => 'Ver usuarios'],
            ['name' => 'users.create',  'display_name' => 'Crear usuarios'],
            ['name' => 'users.update',  'display_name' => 'Actualizar usuarios'],
            ['name' => 'users.delete',  'display_name' => 'Eliminar usuarios'],
            ['name' => 'catalog.manage', 'display_name' => 'Gestionar catálogo'],
            ['name' => 'orders.view',   'display_name' => 'Ver órdenes'],
            ['name' => 'orders.manage', 'display_name' => 'Gestionar estado de órdenes'],
        ];

        foreach ($permissions as $data) {
            Permission::firstOrCreate(['name' => $data['name']], $data);
        }

        // Asigna todos los permisos al rol admin
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->permissions()->sync(Permission::pluck('id'));
        }

        // El editor puede ver y actualizar, pero no crear ni eliminar
        $editor = Role::where('name', 'editor')->first();
        if ($editor) {
            $editorPermissions = Permission::whereIn('name', ['users.view', 'users.update'])->pluck('id');
            $editor->permissions()->sync($editorPermissions);
        }

        // El viewer solo puede ver
        $viewer = Role::where('name', 'viewer')->first();
        if ($viewer) {
            $viewerPermissions = Permission::where('name', 'users.view')->pluck('id');
            $viewer->permissions()->sync($viewerPermissions);
        }
    }
}
