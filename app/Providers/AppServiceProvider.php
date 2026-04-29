<?php

namespace App\Providers;

use App\Helpers\Helper;
use Illuminate\Support\Facades\DB;
use App\Models\Front\Page;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Schema::defaultStringLength(191);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->environment('production')) {
            $canonicalUrl = rtrim((string) config('app.url'), '/');

            if ($canonicalUrl !== '') {
                URL::forceRootUrl($canonicalUrl);

                if (Str::startsWith($canonicalUrl, 'https://')) {
                    URL::forceScheme('https');
                }
            }
        }

        $sharedPages = [
            'uvjeti_kupnje' => collect(),
            'nacini_placanja' => collect(),
        ];

        if ($this->safeHasTable('pages')) {
            $sharedPages = Helper::resolveCache('shared')->remember('front.page_groups', config('cache.life'), function () {
                $pages = Page::query()
                    ->select('id', 'title', 'slug', 'subgroup')
                    ->whereIn('subgroup', ['Uvjeti kupnje', 'Načini plaćanja'])
                    ->orderBy('title')
                    ->get()
                    ->groupBy('subgroup');

                return [
                    'uvjeti_kupnje' => $pages->get('Uvjeti kupnje', collect())->values(),
                    'nacini_placanja' => $pages->get('Načini plaćanja', collect())->values(),
                ];
            });
        }

        View::share('uvjeti_kupnje', $sharedPages['uvjeti_kupnje'] ?? collect());
        View::share('nacini_placanja', $sharedPages['nacini_placanja'] ?? collect());
        View::composer('back.layouts.partials.topbar', function ($view) {
            $view->with('pendingWishlistCount', $this->resolvePendingWishlistCount());
        });

        Paginator::useBootstrap();
    }

    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function resolvePendingWishlistCount(): int
    {
        if (! $this->safeHasTable('wishlist') || ! $this->safeHasTable('products')) {
            return 0;
        }

        try {
            return (int) DB::table('wishlist as w')
                ->join('products as p', 'p.id', '=', 'w.product_id')
                ->where('w.sent', 0)
                ->where('w.status', 1)
                ->where('p.quantity', '!=', 0)
                ->count();
        } catch (Throwable $exception) {
            return 0;
        }
    }
}
