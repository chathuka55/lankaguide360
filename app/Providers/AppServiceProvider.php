<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\Place;
use App\Models\Province;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Import\ImportClient;
use App\Services\Site\SiteCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One client per process: shared rate-limit timers and cache setting for all importers.
        $this->app->singleton(ImportClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Speed: flag N+1 queries while developing (logged, so a missed eager load never breaks a page).
        if ($this->app->isLocal()) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(fn (Model $model, string $relation) => Log::channel('single')->warning(
                'Lazy loading '.$model::class."::{$relation}",
                ['url' => request()?->fullUrl()],
            ));
        }

        // Prices are stored in USD; these print them for people (App\Support\Money):
        // @money($usd) → display currency (LKR by default), @usd($usd), @moneyBoth($usd) for the back office.
        Blade::directive('money', fn (string $expression) => "<?php echo e(\App\Support\Money::show({$expression})); ?>");
        Blade::directive('usd', fn (string $expression) => "<?php echo e(\App\Support\Money::usd({$expression})); ?>");
        Blade::directive('moneyBoth', fn (string $expression) => "<?php echo e(\App\Support\Money::both({$expression})); ?>");
        Blade::directive('approxUsd', fn (string $expression) => "<?php echo e(\App\Support\Money::approxUsd({$expression})); ?>");

        // Short, stable type names in polymorphic columns (media.mediable_type, import_logs.target_type).
        Relation::enforceMorphMap([
            'user' => User::class,
            'district' => District::class,
            'place' => Place::class,
            'hotel' => Hotel::class,
            'package' => Package::class,
            'vehicle' => Vehicle::class,
            'trip' => Trip::class,
        ]);

        // Chatbot: 20 messages per 10 minutes per visitor (SRS 6.5, NFR-09).
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinutes(10, 20)->by($request->ip()));

        // Categories for the footer and chips on every public page (cached).
        View::composer(['components.footer', 'components.navbar', 'home', 'builder.*'], function ($view) {
            $view->with('siteCategories', app(SiteCatalog::class)->categories());
        });
        // Provinces for the navbar's Destinations menu.
        View::composer('components.navbar', function ($view) {
            $view->with('siteProvinces', app(SiteCatalog::class)->provinces());
        });

        foreach ([Category::class, District::class, Province::class] as $model) {
            $model::saved(fn () => SiteCatalog::flush());
            $model::deleted(fn () => SiteCatalog::flush());
        }

        // @can('admin') / Gate::authorize('staff') in views, controllers and policies.
        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('agent', fn (User $user) => $user->isAgent());
        Gate::define('staff', fn (User $user) => $user->isStaff());
    }
}
