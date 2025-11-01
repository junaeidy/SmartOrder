<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Broadcasting\BroadcastException;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Discount;
use App\Models\Announcement;
use App\Policies\ProductPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\DiscountPolicy;
use App\Policies\AnnouncementPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Product::class => ProductPolicy::class,
        Transaction::class => TransactionPolicy::class,
        Discount::class => DiscountPolicy::class,
        Announcement::class => AnnouncementPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
