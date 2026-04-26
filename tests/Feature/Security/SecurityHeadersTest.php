<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Security Headers', function () {
    it('includes Content-Security-Policy header', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertHeader('Content-Security-Policy');
    });

    it('does not include deprecated X-XSS-Protection header', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        expect($response->headers->has('X-XSS-Protection'))->toBeFalse();
    });

    it('still includes X-Content-Type-Options header', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('includes Strict-Transport-Security header', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertHeader('Strict-Transport-Security');
    });

    it('CSP header has a strict policy', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toBeString()->and($csp)->toContain("default-src 'none'");
    });
});
