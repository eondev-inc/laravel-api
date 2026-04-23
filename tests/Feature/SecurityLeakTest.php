<?php

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
    $this->adminPermission = Permission::create(['name' => 'users.view', 'display_name' => 'Ver usuarios']);
    $this->adminRole->permissions()->attach($this->adminPermission->id);

    $this->admin = User::factory()->create();
    $this->admin->roles()->attach($this->adminRole->id);
});

// ─── /api/products ────────────────────────────────────────────────────────────

describe('GET /api/products — no data leaks', function () {
    it('does not expose numeric id in product list', function () {
        Product::factory()->create(['is_active' => true]);

        $body = $this->getJson('/api/products')->assertStatus(200)->content();

        expect($body)->not->toMatch('/"id":\d+/');
    });

    it('does not expose bcrypt hash in product list', function () {
        Product::factory()->create(['is_active' => true]);

        $body = $this->getJson('/api/products')->assertStatus(200)->content();

        expect($body)->not->toContain('$2y$');
    });

    it('exposes uuid as id field in product list', function () {
        $product = Product::factory()->create(['is_active' => true]);

        $data = $this->getJson('/api/products')->assertStatus(200)->json('data.0');

        expect($data['id'])->toBe($product->uuid);
    });
});

// ─── /api/users ───────────────────────────────────────────────────────────────

describe('GET /api/users — no data leaks', function () {
    it('does not expose numeric id in user list', function () {
        Sanctum::actingAs($this->admin);

        $body = $this->getJson('/api/users')->assertStatus(200)->content();

        expect($body)->not->toMatch('/"id":\d+/');
    });

    it('does not expose bcrypt hash in user list', function () {
        Sanctum::actingAs($this->admin);

        $body = $this->getJson('/api/users')->assertStatus(200)->content();

        expect($body)->not->toContain('$2y$');
    });

    it('exposes uuid as id field in user list', function () {
        Sanctum::actingAs($this->admin);

        $data = $this->getJson('/api/users')->assertStatus(200)->json('data.0');

        expect($data['id'])->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
    });
});

// ─── Security headers ─────────────────────────────────────────────────────────

describe('Security headers on API responses', function () {
    it('includes X-Content-Type-Options header', function () {
        $this->getJson('/api/products')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('includes X-Frame-Options header', function () {
        $this->getJson('/api/products')
            ->assertHeader('X-Frame-Options', 'DENY');
    });

    it('includes X-XSS-Protection header', function () {
        $this->getJson('/api/products')
            ->assertHeader('X-XSS-Protection', '1; mode=block');
    });

    it('includes Strict-Transport-Security header', function () {
        $this->getJson('/api/products')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    });
});

// ─── Rate limiting ─────────────────────────────────────────────────────────────

describe('API Rate Limiting', function () {
    it('returns 429 after 60 requests per minute', function () {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/products');
        }

        $this->getJson('/api/products')->assertStatus(429);
    });
});

// ─── CORS ─────────────────────────────────────────────────────────────────────

describe('CORS strict origin policy', function () {
    it('does not echo back disallowed origins in CORS header', function () {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.com',
        ])->getJson('/api/products');

        $corsHeader = $response->headers->get('Access-Control-Allow-Origin');
        expect($corsHeader)->not->toBe('https://evil.com');
    });
});
