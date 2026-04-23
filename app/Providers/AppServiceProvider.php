<?php

namespace App\Providers;

use App\Contracts\Payments\TransbankGateway;
use App\Services\TransbankService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Transaction;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TransbankGateway::class, function () {
            $env = config('app.env');

            if ($env === 'production') {
                $transaction = Transaction::buildForProduction(
                    apiKey: config('services.transbank.api_key'),
                    commerceCode: config('services.transbank.commerce_code'),
                );
            } else {
                $transaction = Transaction::buildForIntegration(
                    apiKey: WebpayPlus::INTEGRATION_API_KEY,
                    commerceCode: WebpayPlus::INTEGRATION_COMMERCE_CODE,
                );
            }

            return new TransbankService($transaction);
        });
    }

    public function boot(): void
    {
        Gate::define('viewApiDocs', function ($user = null) {
            return app()->environment('local');
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
