<?php

namespace App\Providers;

use App\Contracts\CourierAdapter;
use App\Contracts\PaymentGateway;
use App\Contracts\Repositories\AuctionRepository;
use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\GoogleProductTaxonomyRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Couriers\ManualCourierAdapter;
use App\Payments\StripePaymentGateway;
use App\Repositories\EloquentAuctionRepository;
use App\Repositories\EloquentCatalogRepository;
use App\Repositories\EloquentGoogleProductTaxonomyRepository;
use App\Repositories\EloquentListingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuctionRepository::class, EloquentAuctionRepository::class);
        $this->app->bind(CatalogRepository::class, EloquentCatalogRepository::class);
        $this->app->bind(GoogleProductTaxonomyRepository::class, EloquentGoogleProductTaxonomyRepository::class);
        $this->app->bind(ListingRepository::class, EloquentListingRepository::class);
        $this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
        $this->app->bind(CourierAdapter::class, ManualCourierAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('auction-bids', fn ($request) => Limit::perMinute(12)->by($request->user()?->id.'|'.$request->ip()));
    }
}
