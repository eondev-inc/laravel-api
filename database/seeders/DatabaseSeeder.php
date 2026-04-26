<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Design;
use App\Models\Product;
use App\Models\ProductVariation;
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
        Category::factory(5)->create()->each(function ($category) {
            Product::factory(10)->create([
                'category_id' => $category->id,
            ])->each(function ($product) {
                // Generar algunas variaciones (talles/colores) por producto
                ProductVariation::factory(3)->create([
                    'product_id' => $product->id,
                ]);
            });
        });

        // Generar diseños
        Design::factory(20)->create();
    }
}
