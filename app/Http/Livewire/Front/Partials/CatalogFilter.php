<?php

namespace App\Http\Livewire\Front\Partials;

use App\Helpers\Query;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Class CatalogFilter
 * @package App\Http\Livewire\Front\Partials
 */
class CatalogFilter extends Component
{

    /**
     * @var null
     */
    public $group = null;

    /**
     * @var null
     */
    public $category = null;

    /**
     * @var null
     */
    public $subcategory = null;

    /**
     * @var null
     */
    public $categories = [];

    /**
     * @var
     */
    public $ids;

    /**
     * @var
     */
    public $authors = [];

    /**
     * @var
     */
    public $author;

    /**
     * @var
     */
    public $products;

    /**
     * @var
     */
    public $publishers = [];

    /**
     * @var
     */
    public $publisher;

    /**
     * @var string
     */
    public $autor = '';

    /**
     * @var string
     */
    public $nakladnik = '';

    /**
     * @var
     */
    public $start;

    /**
     * @var
     */
    public $end;

    /**
     * @var
     */
    public $searcha;
    /**
     * @var
     */
    public $searchp;

    /**
     * @var \string[][]
     */
    protected $queryString = [
        /*'autor' => ['except' => ''],
        'nakladnik' => ['except' => ''],*/
        'start' => ['except' => ''],
        'end' => ['except' => '']
    ];


    /**
     *
     */
    public function mount()
    {
        $this->getBaseIDs();
        $this->mountQuery();
    }


    /*public function updatedAuthor()
    {
        $this->resolveQuery();
    }
    public function updatedPublisher()
    {
        $this->resolveQuery();
    }*/

    /**
     * @param $value
     */
    public function updatingSearcha($value)
    {
        $this->searcha = $value;
        $this->authors = [];

        if ($this->searcha != '') {
            $this->authors = Author::active()->where('title', 'LIKE', '%' . $this->searcha . '%')
                                   ->select('id', 'title', 'url')
                                   ->withCount('products')
                                   ->orderBy('title')
                                   ->limit(5)
                                   ->get();
        }

    }


    /**
     * @param $value
     */
    public function updatingSearchp($value)
    {
        $this->searchp    = $value;
        $this->publishers = [];

        if ($this->searchp != '') {
            $this->publishers = Publisher::active()->where('title', 'LIKE', '%' . $this->searchp . '%')
                                         ->select('id', 'title', 'url')
                                         ->withCount('products')
                                         ->orderBy('title')
                                         ->limit(5)
                                         ->get();
        }

    }


    /**
     *
     */
    public function updatedStart()
    {
        $this->resolveQuery();
    }


    /**
     *
     */
    public function updatedEnd()
    {
        $this->resolveQuery();
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        $this->emit('idChanged', [
            'ids' => $this->ids,
            /*'author' => $this->author,
            'publisher' => $this->publisher,*/
            'start' => $this->start,
            'end' => $this->end
        ]);

        return view('livewire.front.partials.catalog-filter');
    }


    /**
     * @param string $target
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function resolveRoute(string $target)
    {
        return redirect($target);
    }


    /**
     *
     */
    private function mountQuery()
    {
        if ($this->autor != '') {
            $this->author = Query::mountAuthor($this->autor);
        }

        if ($this->nakladnik != '') {
            $this->publisher = Query::mountPublisher($this->nakladnik);
        }
    }


    /**
     *
     */
    private function resolveQuery()
    {
        if ($this->author) {
            $this->author = Query::unset($this->author);
            $this->autor = Query::resolve($this->author);
        }

        if ($this->publisher) {
            $this->publisher = Query::unset($this->publisher);
            $this->nakladnik = Query::resolve($this->publisher);
        }
    }


    /**
     * @return \Illuminate\Support\Collection
     */
    private function getBaseIDs()
    {
        if ($this->group) {
            if ( ! $this->category && ! $this->subcategory) {
                $response = [];
                $categories = Cache::remember('category_list.' . $this->group, config('cache.life'), function () {
                    return Category::where('group', $this->group)->where('parent_id', 0)->sortByName()->with('subcategories')->withCount('products')->get()->toArray();
                });

                foreach ($categories as $category) {
                    $response[] = [
                        'id' => $category['id'],
                        'title' => $category['title'],
                        'count' => $category['products_count'],
                        'url' => route('catalog.route', ['group' => Str::slug($category['group']), 'cat' => $category['slug']])
                    ];
                }

                $this->categories = $response;
            }

            //
            if ($this->category && ! $this->subcategory) {
                $item = Cache::remember('category_list.' . $this->category->id, config('cache.life'), function () {
                    return Category::where('parent_id', $this->category->id)->sortByName()->with('subcategories')->withCount('products')->get()->toArray();
                });

                if ($item) {
                    $response = [];

                    foreach ($item as $category) {
                        $response[] = [
                            'id' => $category['id'],
                            'title' => $category['title'],
                            'count' => $category['products_count'],
                            'url' => route('catalog.route', ['group' => Str::slug($category['group']), 'cat' => $this->category['slug'], 'subcat' => $category['slug']])
                        ];
                    }

                    $this->categories = $response;
                }
            }
        }
    }
}
