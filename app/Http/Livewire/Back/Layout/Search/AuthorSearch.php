<?php

namespace App\Http\Livewire\Back\Layout\Search;

use App\Models\Back\Catalog\Author;
use App\Services\Catalog\AuthorResolver;
use Livewire\Component;

class AuthorSearch extends Component
{

    /**
     * @var string
     */
    public $search = '';

    /**
     * @var array
     */
    public $search_results = [];

    /**
     * @var int
     */
    public $author_id = 0;

    /**
     * @var bool
     */
    public $show_add_window = false;

    /**
     * @var null|bool
     */
    public $list = null;

    /**
     * @var array
     */
    public $new = [
        'title' => ''
    ];


    /**
     *
     */
    public function mount()
    {
        if ($this->author_id) {
            $author = Author::find($this->author_id);

            if ($author) {
                $this->search = $author->title;
            }
        }
    }


    /**
     *
     */
    public function viewAddWindow()
    {
        $this->show_add_window = ! $this->show_add_window;
    }


    /**
     *
     */
    public function updatingSearch($value)
    {
        $this->search         = $value;
        $this->search_results = [];

        if ($this->search != '') {
            $this->search_results = (new Author())->where('title', 'LIKE', '%' . $this->search . '%')
                                                  ->limit(5)
                                                  ->get();
        }
    }


    /**
     * @param $id
     */
    public function addAuthor($id)
    {
        $author = (new Author())->where('id', $id)->first();

        $this->search_results = [];
        $this->search         = $author->title;
        $this->author_id     = $author->id;

        if ($this->list) {
            return $this->emit('authorSelect', ['author' => $author->toArray()]);
        }
    }


    /**
     *
     */
    public function makeNewAuthor()
    {
        $title = AuthorResolver::normalizeName((string) ($this->new['title'] ?? ''));

        if ($title === '') {
            return $this->emit('error_alert', ['message' => 'Molimo vas da popunite sve podatke!']);
        }

        $existing = Author::query()
            ->where('normalized_title', AuthorResolver::normalizedKey($title))
            ->orderBy('id')
            ->first();

        $id = app(AuthorResolver::class)->resolveName($title);

        if ($id) {
            $author = Author::find($id);

            $this->show_add_window = false;

            $this->author_id = $author->id;
            $this->search     = $author->title;
            $this->new['title'] = '';

            if ($existing) {
                return $this->emit('success_alert', ['message' => 'Postojeći autor je odabran.']);
            }

            return $this->emit('success_alert', ['message' => 'Autor je uspješno dodan..!']);
        }

        return $this->emit('error_alert');
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        if ($this->search == '') {
            $this->author_id = 0;

            if ($this->list) {
                $this->emit('authorSelect', ['author' => ['id' => '']]);
            }
        }

        return view('livewire.back.layout.search.author-search');
    }
}
