<?php

namespace App\Providers;

use App\Contracts\CourierAdapter;
use App\Contracts\PaymentGateway;
use App\Contracts\Repositories\AuctionRepository;
use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\GoogleProductTaxonomyRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\ListingVariantRepository;
use App\Contracts\Repositories\OrderTrackingRepository;
use App\Contracts\Repositories\ProductQuestionRepository;
use App\Contracts\Repositories\PromotionRepository;
use App\Contracts\Repositories\RefundRepository;
use App\Contracts\Repositories\ReturnRequestRepository;
use App\Contracts\Repositories\ReviewRepository;
use App\Contracts\Repositories\WatchlistRepository;
use App\Couriers\ManualCourierAdapter;
use App\Models\User;
use App\Payments\StripePaymentGateway;
use App\Repositories\EloquentAuctionRepository;
use App\Repositories\EloquentCatalogRepository;
use App\Repositories\EloquentGoogleProductTaxonomyRepository;
use App\Repositories\EloquentListingRepository;
use App\Repositories\EloquentListingVariantRepository;
use App\Repositories\EloquentOrderTrackingRepository;
use App\Repositories\EloquentProductQuestionRepository;
use App\Repositories\EloquentPromotionRepository;
use App\Repositories\EloquentRefundRepository;
use App\Repositories\EloquentReturnRequestRepository;
use App\Repositories\EloquentReviewRepository;
use App\Repositories\EloquentWatchlistRepository;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
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
        $this->app->bind(ListingVariantRepository::class, EloquentListingVariantRepository::class);
        $this->app->bind(PromotionRepository::class, EloquentPromotionRepository::class);
        $this->app->bind(ProductQuestionRepository::class, EloquentProductQuestionRepository::class);
        $this->app->bind(OrderTrackingRepository::class, EloquentOrderTrackingRepository::class);
        $this->app->bind(ReviewRepository::class, EloquentReviewRepository::class);
        $this->app->bind(RefundRepository::class, EloquentRefundRepository::class);
        $this->app->bind(ReturnRequestRepository::class, EloquentReturnRequestRepository::class);
        $this->app->bind(WatchlistRepository::class, EloquentWatchlistRepository::class);
        $this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
        $this->app->bind(CourierAdapter::class, ManualCourierAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMailNotifications();
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
        RateLimiter::for('category-lookups', fn ($request) => Limit::perMinute(120)->by($request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('category-suggestions', fn ($request) => Limit::perMinute(30)->by($request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('listing-content-suggestions', fn ($request) => Limit::perMinute(20)->by($request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('order-tracking', fn ($request) => Limit::perMinute(8)->by($request->ip()));
    }

    protected function configureMailNotifications(): void
    {
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $expirationMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Reset your ProDeals.lk password')
                ->greeting("Hello {$user->name},")
                ->line('We received a request to reset the password for your ProDeals.lk account.')
                ->action('Reset password', $resetUrl)
                ->line("This secure link expires in {$expirationMinutes} minutes.")
                ->line('If you did not request this reset, you can safely ignore this email. Your password will not change.');
        });

        VerifyEmail::toMailUsing(function (User $user, string $verificationUrl): MailMessage {
            $expirationMinutes = (int) config('auth.verification.expire', 60);

            return (new MailMessage)
                ->subject('Confirm your ProDeals.lk email address')
                ->greeting("Hello {$user->name},")
                ->line('Confirm your email address to finish setting up your ProDeals.lk account.')
                ->action('Confirm email address', $verificationUrl)
                ->line("This secure link expires in {$expirationMinutes} minutes.")
                ->line('If you did not create this account, you can safely ignore this email.');
        });
    }
}
