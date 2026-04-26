<?php

use App\Providers\AppServiceProvider;

describe('Redis requirement for production', function () {
    it('does not throw when cache driver is redis in production', function () {
        config([
            'app.env' => 'production',
            'cache.default' => 'redis',
        ]);

        $provider = new AppServiceProvider(app());

        expect(fn () => $provider->boot())->not->toThrow(RuntimeException::class);
    });

    it('throws RuntimeException when cache driver is not redis in production', function () {
        config([
            'app.env' => 'production',
            'cache.default' => 'file',
        ]);

        $provider = new AppServiceProvider(app());

        expect(fn () => $provider->boot())->toThrow(
            RuntimeException::class,
            'Redis'
        );
    });

    it('does not throw when cache driver is not redis in non-production environments', function () {
        config([
            'app.env' => 'local',
            'cache.default' => 'file',
        ]);

        $provider = new AppServiceProvider(app());

        expect(fn () => $provider->boot())->not->toThrow(RuntimeException::class);
    });

    it('does not throw in testing environment even without redis', function () {
        config([
            'app.env' => 'testing',
            'cache.default' => 'array',
        ]);

        $provider = new AppServiceProvider(app());

        expect(fn () => $provider->boot())->not->toThrow(RuntimeException::class);
    });
});
