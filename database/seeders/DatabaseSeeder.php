<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Orden importante: roles → permissions → users
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
        ]);

        // Generar datos de prueba para el catálogo y la tienda
        \App\Models\Category::factory(5)->create()->each(function ($category) {
            \App\Models\Product::factory(10)->create([
                'category_id' => $category->id,
            ])->each(function ($product) {
                // Generar algunas variaciones (talles/colores) por producto
                \App\Models\ProductVariation::factory(3)->create([
                    'product_id' => $product->id,
                ]);
            });
        });

        // Generar diseños
        \App\Models\Design::factory(20)->create();
    }
}
