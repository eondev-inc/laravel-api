<?php

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    RateLimiter::clear('uploads');

    $designerRole = Role::create(['name' => 'designer', 'display_name' => 'Diseñador']);
    $designPerm = Permission::create(['name' => 'designs.create', 'display_name' => 'Crear diseños']);
    $designerRole->permissions()->attach($designPerm->id);

    $this->designerRole = $designerRole;
});

describe('Design upload rate limiting', function () {
    it('returns 429 when exceeding 10 uploads per minute', function () {
        $user = User::factory()->create();
        $user->roles()->attach($this->designerRole->id);
        $product = Product::factory()->create();

        Sanctum::actingAs($user);

        // Hit the limit
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/designs', [
                'name' => "Design {$i}",
                'product_id' => $product->uuid,
                'image' => UploadedFile::fake()->create("d{$i}.png", 10, 'image/png'),
            ])->assertStatus(201);
        }

        // 11th request must be rate-limited
        $this->postJson('/api/designs', [
            'name' => 'Over limit',
            'product_id' => $product->uuid,
            'image' => UploadedFile::fake()->create('over.png', 10, 'image/png'),
        ])->assertStatus(429);
    });
});
