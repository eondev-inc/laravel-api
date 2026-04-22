<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin',  'display_name' => 'Administrador', 'description' => 'Acceso total al sistema'],
            ['name' => 'editor', 'display_name' => 'Editor',        'description' => 'Puede ver y actualizar recursos'],
            ['name' => 'viewer', 'display_name' => 'Lector',        'description' => 'Solo lectura'],
        ];

        foreach ($roles as $data) {
            Role::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
