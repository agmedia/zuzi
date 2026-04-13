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

        $uvjeti_kupnje = Schema::hasTable('pages')
            ? Page::where('subgroup', 'Uvjeti kupnje')->get()
            : collect();
        View::share('uvjeti_kupnje', $uvjeti_kupnje);

        $nacini_placanja = Schema::hasTable('pages')
            ? Page::where('subgroup', 'Načini plaćanja')->get()
            : collect();
        View::share('nacini_placanja', $nacini_placanja);

        $products = Schema::hasTable('products')
            ? Product::active()->hasStock()->count()
            : 0;
        View::share('products', $products);

        $users = Schema::hasTable('users')
            ? User::count()
            : 0;
        View::share('users', $users);

        $knjige = Schema::hasTable('categories')
            ? Category::active()->topList(Helper::categoryGroupPath(true))->sortByName()->select('id', 'title', 'group', 'slug')->get()
            : collect();
        View::share('knjige', $knjige);

        $kategorijefeatured = Schema::hasTable('categories')
            ? Category::active()->where('image', '!=', 'media/avatars/avatar0.jpg')->sortByName()->select('id','image','title', 'group', 'slug')->get()
            : collect();
        View::share('kategorijefeatured', $kategorijefeatured);

        $zemljovidi_vedute = Schema::hasTable('categories')
            ? Category::active()->topList('Zemljovidi i vedute')->select('id', 'title', 'group', 'slug')->sortByName()->get()
            : collect();
        View::share('zemljovidi_vedute', $zemljovidi_vedute);

        Paginator::useBootstrap();
    }
}
