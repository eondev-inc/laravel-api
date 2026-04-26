<?php

describe('SetRequestId middleware', function () {
    it('adds X-Request-ID header to response', function () {
        $response = $this->getJson('/up');

        $response->assertStatus(200);
        expect($response->headers->has('X-Request-ID'))->toBeTrue();
        expect($response->headers->get('X-Request-ID'))->not->toBeEmpty();
    });

    it('generates a valid UUID v4 as request ID', function () {
        $response = $this->getJson('/up');

        $requestId = $response->headers->get('X-Request-ID');
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        expect(preg_match($uuidPattern, $requestId))->toBe(1);
    });

    it('respects upstream X-Request-ID if provided', function () {
        $upstreamId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->withHeaders(['X-Request-ID' => $upstreamId])
            ->getJson('/up');

        expect($response->headers->get('X-Request-ID'))->toBe($upstreamId);
    });

    it('generates a new ID when no upstream X-Request-ID is provided', function () {
        $response1 = $this->getJson('/up');
        $response2 = $this->getJson('/up');

        $id1 = $response1->headers->get('X-Request-ID');
        $id2 = $response2->headers->get('X-Request-ID');

        expect($id1)->not->toBe($id2);
    });
});
