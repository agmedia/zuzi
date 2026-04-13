<?php

namespace App\Providers;

use App\Helpers\Helper;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Page;
use App\Models\User;
use App\Models\Front\Catalog\Product;
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

        $hasPages = $this->safeHasTable('pages');
        $hasProducts = $this->safeHasTable('products');
        $hasUsers = $this->safeHasTable('users');
        $hasCategories = $this->safeHasTable('categories');

        $uvjeti_kupnje = $hasPages
            ? Page::where('subgroup', 'Uvjeti kupnje')->get()
            : collect();
        View::share('uvjeti_kupnje', $uvjeti_kupnje);

        $nacini_placanja = $hasPages
            ? Page::where('subgroup', 'Načini plaćanja')->get()
            : collect();
        View::share('nacini_placanja', $nacini_placanja);

        $products = $hasProducts
            ? Product::active()->hasStock()->count()
            : 0;
        View::share('products', $products);

        $users = $hasUsers
            ? User::count()
            : 0;
        View::share('users', $users);

        $knjige = $hasCategories
            ? Category::active()->topList(Helper::categoryGroupPath(true))->sortByName()->select('id', 'title', 'group', 'slug')->get()
            : collect();
        View::share('knjige', $knjige);

        $kategorijefeatured = $hasCategories
            ? Category::active()->where('image', '!=', 'media/avatars/avatar0.jpg')->sortByName()->select('id','image','title', 'group', 'slug')->get()
            : collect();
        View::share('kategorijefeatured', $kategorijefeatured);

        $zemljovidi_vedute = $hasCategories
            ? Category::active()->topList('Zemljovidi i vedute')->select('id', 'title', 'group', 'slug')->sortByName()->get()
            : collect();
        View::share('zemljovidi_vedute', $zemljovidi_vedute);

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
}
