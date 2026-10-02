<?php

namespace App\Providers;

use App\Auth\AppleProvider;
use App\Models\Category;
use App\Models\Listing;
use App\Services\AreaService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\RelicAi::class);
        $this->app->singleton(\App\Services\RelicOpsAi::class);
        $this->app->singleton(\App\Services\RelicCareAi::class);
        $this->app->singleton(\App\Services\LlmClient::class);
        $this->app->singleton(\App\Services\RelicCareKnowledge::class);
        $this->app->singleton(\App\Services\RelicCareBrain::class);
        $this->app->singleton(\App\Ai\Engine::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination.relic');
        Paginator::defaultSimpleView('pagination.relic');

        $root = rtrim((string) config('app.url'), '/');
        if ($root !== '') {
            URL::forceRootUrl($root);
            URL::forceScheme(parse_url($root, PHP_URL_SCHEME) ?: 'http');
        }

        Socialite::extend('apple', function ($app) {
            $config = $app['config']['services.apple'];

            return Socialite::buildProvider(AppleProvider::class, $config);
        });

        Broadcast::routes(['middleware' => ['web', 'auth']]);

        View::composer(['layouts.store', 'layouts.admin', 'layouts.account'], function ($view) {
            $view->with('navCategories', Category::activeCached());
            $view->with('cartCount', collect(session('cart', []))->count());
            $view->with('favoriteIds', auth()->check()
                ? auth()->user()->favorites()->pluck('listing_id')->all()
                : []);
            $view->with('areas', AreaService::names());
            $city = request()->query('city');
            $view->with('currentArea', AreaService::isValid(is_string($city) ? $city : null) ? $city : null);
        });

        View::composer('listings._promo', function ($view) {
            $terms = collect(session('relic.searches', []))->take(4)->values();
            $city = request()->query('city');
            $area = AreaService::isValid(is_string($city) ? $city : null) ? $city : null;

            $promo = Listing::public()->with(['images', 'brand', 'category', 'origin'])
                ->inArea($area)
                ->when($terms->isNotEmpty(), fn ($q) => $q->where(function ($inner) use ($terms) {
                    foreach ($terms as $term) {
                        $inner->orWhere('title', 'like', '%' . $term . '%')
                            ->orWhere('model', 'like', '%' . $term . '%');
                    }
                }))
                ->orderByDesc('views')
                ->take(6)
                ->get();

            if ($promo->count() < 3) {
                $promo = $promo->concat(
                    Listing::public()->with(['images', 'brand', 'category', 'origin'])
                        ->inArea($area)
                        ->orderByDesc('views')->take(6)->get()
                )->unique('id')->take(6)->values();
            }

            $view->with('promoListings', $promo)->with('promoTerms', $terms);
        });
    }
}
