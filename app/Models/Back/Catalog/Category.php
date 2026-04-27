<?php

namespace App\Models\Back\Catalog;

use App\Helpers\Helper;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductCategory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class Category extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'categories';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var Request
     */
    protected $request;


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subcategories()
    {
        return $this->hasMany(Category::class, 'parent_id', 'id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function products()
    {
        return $this->hasManyThrough(Product::class, ProductCategory::class, 'category_id', 'id', 'id', 'product_id');
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }


    /**
     * @param Builder $query
     * @param string  $group
     *
     * @return Builder
     */
    public function scopeTopList(Builder $query, string $group = ''): Builder
    {
        if ( ! empty($group)) {
            return $query->where('group', $group)->where('parent_id', '==', 0);
        }

        return $query->where('parent_id', '==', 0);
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeGroups(Builder $query): Builder
    {
        return $query->groupBy('group');
    }


    /**
     * @param bool $full
     *
     * @return Collection
     */
    public function getList(bool $full = true): Collection
    {
        return Helper::rememberCache($this->getListCacheKey($full), now()->addMinutes(30), function () use ($full) {
            $topCategories = $this->newQuery()
                                  ->where('parent_id', 0)
                                  ->orderBy('group')
                                  ->orderBy('title')
                                  ->with(['subcategories' => function ($query) {
                                      $query->select(['id', 'parent_id', 'title'])
                                            ->orderBy('title');
                                  }]);

            if ($full) {
                return $topCategories->withCount('products')
                                     ->get(['id', 'group', 'title'])
                                     ->groupBy('group');
            }

            return $topCategories->get(['id', 'group', 'title'])
                                 ->groupBy('group')
                                 ->map(function (Collection $categories) {
                                     return $categories->mapWithKeys(function (Category $category) {
                                         return [
                                             $category->id => [
                                                 'title' => $category->title,
                                                 'subs'  => $category->subcategories
                                                     ->mapWithKeys(fn (Category $subcategory) => [$subcategory->id => ['title' => $subcategory->title]])
                                                     ->all(),
                                             ]
                                         ];
                                     });
                                 });
        });
    }


    /**
     * Briše admin cache liste kategorija nakon promjena.
     */
    public static function forgetAdminListCache(): void
    {
        Helper::forgetCache(static::getListCacheKey(true));
        Helper::forgetCache(static::getListCacheKey(false));
    }


    /**
     * Cache key za admin dohvat kategorija.
     */
    protected static function getListCacheKey(bool $full): string
    {
        return 'admin_categories_list.' . ($full ? 'full' : 'compact');
    }


    /**
     * Validate new category Request.
     *
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        $request->validate([
            'title' => 'required'
        ]);

        $this->request = $request;

        return $this;
    }


    /**
     * Store new category.
     *
     * @return false
     */
    public function create()
    {
        $parent = $this->request->parent ?: 0;
        $group  = isset($this->request->group) ? $this->request->group : 0;

        if ($parent) {
            $topcat = $this->where('id', $parent)->first();
            $group  = $topcat->group;
        }

        $id = $this->insertGetId([
            'parent_id'        => $parent,
            'title'            => $this->request->title,
            'description'      => $this->request->description,
            'meta_title'       => $this->request->meta_title,
            'meta_description' => $this->request->meta_description,
            'group'            => $group,
            'lang'             => 'hr',
            'status'           => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'slug'             => isset($this->request->slug) ? Str::slug($this->request->slug) : Str::slug($this->request->title),
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now()
        ]);

        if ($id) {
            return $this->find($id);
        }

        return false;
    }


    /**
     * @param Category $category
     *
     * @return false
     */
    public function edit()
    {
        $parent = $this->request->parent ?: 0;
        $group  = isset($this->request->group) ? $this->request->group : 0;

        if ($parent) {
            $topcat = $this->where('id', $parent)->first();
            $group  = $topcat->group;
        }

        $id = $this->update([
            'parent_id'        => $parent,
            'title'            => $this->request->title,
            'description'      => $this->request->description,
            'meta_title'       => $this->request->meta_title,
            'meta_description' => $this->request->meta_description,
            'group'            => $group,
            'lang'             => 'hr',
            'status'           => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'slug'             => isset($this->request->slug) ? Str::slug($this->request->slug) : Str::slug($this->request->title),
            'updated_at'       => Carbon::now()
        ]);

        if ($id) {
            return $this;
        }

        return false;
    }


    /**
     * @param Category $category
     *
     * @return bool
     */
    public function resolveImage(Category $category)
    {
        if ($this->request->hasFile('image')) {
            $name = Str::slug($category->title) . '.' . $this->request->image->extension();

            $this->request->image->storeAs('/', $name, 'category');

            return $category->update([
                'image' => config('filesystems.disks.category.url') . $name
            ]);
        }

        return false;
    }

}
