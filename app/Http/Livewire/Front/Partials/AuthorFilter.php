<?php

namespace App\Http\Livewire\Front\Partials;

use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Publisher;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Class AuthorFilter
 * @package App\Http\Livewire\Front\Partials
 */
class AuthorFilter extends Component
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
    public $categories = null;

    /**
     * @var
     */
    public $ids;

    /**
     * @var
     */
    public $products;

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
    public $selected_author;

    /**
     * @var
     */
    public $publishers = [];

    /**
     * @var
     */
    public $publisher;

    /**
     * @var
     */
    public $selected_publisher;

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
        $this->setCategories();

    }


    /**
     * @param $value
     */
    public function updatingSearcha($value)
    {
        $this->searcha = $value;
        $this->authors = [];

        if ($this->searcha != '') {
            $this->authors = Author::active()
                                   ->where('title', 'LIKE', '%' . $this->searcha . '%')
                                   ->select('id', 'title', 'url')
                                   ->withCount('products')
                                   ->having('products_count', '>', 0)
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
            $this->publishers = Publisher::active()
                                         ->where('title', 'LIKE', '%' . $this->searchp . '%')
                                         ->select('id', 'title', 'url')
                                         ->withCount('products')
                                         ->having('products_count', '>', 0)
                                         ->limit(5)
                                         ->get();
        }

    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        $this->emit('idChanged', [
            'ids' => $this->ids,
            /*'author' => $this->authors,
            'publisher' => $this->publishers,*/
            'start' => $this->start,
            'end' => $this->end
        ]);

        return view('livewire.front.partials.author-filter');
    }


    /**
     *
     */
    private function setCategories()
    {
        // AKo su autori
        if ($this->selected_author) {
            $categories = $this->cache('selected_author');
            $response = $this->traverse('selected_author', $categories);
        }

        // Ako su nakladnici
        if ($this->selected_publisher) {
            $categories = $this->cache('selected_publisher');
            $response = $this->traverse('selected_publisher', $categories);
        }

        $this->categories = $response;

        if ($this->subcategory) {
            $this->categories = null;
        }
    }


    /**
     * @param string $model
     *
     * @return mixed
     */
    private function cache(string $model)
    {
        if ($this->category) {
            return Cache::remember('category_list.' . $this->{$model}->id . '.' . $this->category->id, config('cache.life'), function () use ($model) {
                return $this->{$model}->categories($this->category->id);
            });
        } else {
            return Cache::remember('category_list.' . $this->{$model}->id . '.0', config('cache.life'), function () use ($model) {
                return $this->{$model}->categories();
            });
        }
    }


    /**
     * @param string $model
     * @param        $categories
     *
     * @return array
     */
    private function traverse(string $model, $categories)
    {
        $target = str_replace('selected_', '', $model);

        $response = [];

        foreach ($categories as $category) {
            $response[] = [
                'id' => $category['id'],
                'title' => $category['title'],
                'count' => $category['products_count'],
                'url' => route('catalog.route.' . $target, [$target => $this->{$model}, 'cat' => ($category->parent ?: $category), 'subcat' => ($category->parent ? $category : $category->parent)])
            ];
        }

        return $response;
    }
}
