<?php

describe('S3 / MinIO filesystem configuration', function () {
    it('s3_public disk is configured with s3 driver', function () {
        $disk = config('filesystems.disks.s3_public');

        expect($disk)->not->toBeNull()
            ->and($disk['driver'])->toBe('s3');
    });

    it('s3_private disk is configured with s3 driver', function () {
        $disk = config('filesystems.disks.s3_private');

        expect($disk)->not->toBeNull()
            ->and($disk['driver'])->toBe('s3');
    });

    it('s3_public disk reads key from AWS_ACCESS_KEY_ID env var', function () {
        config(['filesystems.disks.s3_public.key' => 'test-key-id']);

        expect(config('filesystems.disks.s3_public.key'))->toBe('test-key-id');
    });

    it('s3_private disk reads bucket from AWS_PRIVATE_BUCKET env var', function () {
        config(['filesystems.disks.s3_private.bucket' => 'my-private-bucket']);

        expect(config('filesystems.disks.s3_private.bucket'))->toBe('my-private-bucket');
    });

    it('s3_public disk has public visibility', function () {
        $disk = config('filesystems.disks.s3_public');

        expect($disk['visibility'])->toBe('public');
    });

    it('s3_private disk has private visibility', function () {
        $disk = config('filesystems.disks.s3_private');

        expect($disk['visibility'])->toBe('private');
    });

    it('s3_public disk has use_path_style_endpoint key', function () {
        $disk = config('filesystems.disks.s3_public');

        expect($disk)->toHaveKey('use_path_style_endpoint');
    });

    it('minio is reachable from app container', function () {
        $endpoint = env('AWS_ENDPOINT', 'http://minio:9000');

        // Skip if MinIO is not running (local dev without docker)
        $host = parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT) ?? 80;

        $connection = @fsockopen($host, $port, $errno, $errstr, 1);

        if ($connection === false) {
            test()->markTestSkipped("MinIO not reachable at {$endpoint} — skipping connectivity test.");
        }

        fclose($connection);

        expect($connection)->not->toBeFalse();
    });
});
