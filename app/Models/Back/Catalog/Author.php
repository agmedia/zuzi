<?php

namespace App\Models\Back\Catalog;

use App\Helpers\Helper;
use App\Models\Back\Catalog\Product\Product;
use App\Services\Catalog\AuthorResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Author extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'authors';

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
    public function products()
    {
        return $this->hasMany(Product::class, 'author_id', 'id');
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
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
            'title' => [
                'required',
                'string',
                'max:191',
                function ($attribute, $value, $fail): void {
                    $title = AuthorResolver::normalizeName((string) $value);
                    if ($title === '') {
                        $fail('Molimo unesite ime autora.');

                        return;
                    }

                    $query = self::query()
                        ->where('normalized_title', AuthorResolver::normalizedKey($title));

                    if ($this->exists) {
                        $query->where('id', '<>', $this->getKey());
                    }

                    if ($query->exists()) {
                        $fail('Autor s tim imenom već postoji.');
                    }
                },
            ],
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
        $title = AuthorResolver::normalizeName((string) $this->request->title);
        $slug = isset($this->request->slug) ? Str::slug($this->request->slug) : Str::slug($title);

        try {
            $id = $this->insertGetId([
                'letter'           => Helper::resolveFirstLetter($title),
                'title'            => $title,
                'normalized_title' => AuthorResolver::normalizedKey($title),
                'description'      => $this->request->description,
                'meta_title'       => $this->request->meta_title,
                'meta_description' => $this->request->meta_description,
                'lang'             => 'hr',
                'sort_order'       => 0,
                'status'           => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
                'featured'         => (isset($this->request->featured) and $this->request->featured == 'on') ? 1 : 0,
                'slug'             => $slug,
                'url'              => config('settings.author_path') . '/' . $slug,
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now()
            ]);
        } catch (QueryException $exception) {
            $this->throwDuplicateValidation($exception);
        }

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
        $title = AuthorResolver::normalizeName((string) $this->request->title);
        $slug = isset($this->request->slug) ? Str::slug($this->request->slug) : Str::slug($title);

        try {
            $id = $this->update([
                'letter'           => Helper::resolveFirstLetter($title),
                'title'            => $title,
                'normalized_title' => AuthorResolver::normalizedKey($title),
                'description'      => $this->request->description,
                'meta_title'       => $this->request->meta_title,
                'meta_description' => $this->request->meta_description,
                'lang'             => 'hr',
                'sort_order'       => 0,
                'status'           => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
                'featured'         => (isset($this->request->featured) and $this->request->featured == 'on') ? 1 : 0,
                'slug'             => $slug,
                'url'              => config('settings.author_path') . '/' . $slug,
                'updated_at'       => Carbon::now()
            ]);
        } catch (QueryException $exception) {
            $this->throwDuplicateValidation($exception);
        }

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
    public function resolveImage(Author $author)
    {
        if ($this->request->hasFile('image')) {
            $name = Str::slug($author->title) . '.' . $this->request->image->extension();

            $this->request->image->storeAs('/', $name, 'publisher');

            return $author->update([
                'image' => config('filesystems.disks.author.url') . $name
            ]);
        }

        return false;
    }


    private function throwDuplicateValidation(QueryException $exception): void
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($sqlState === '23505'
            || ($sqlState === '23000' && in_array($driverCode, [19, 1062], true))) {
            throw ValidationException::withMessages([
                'title' => 'Autor s tim imenom već postoji.',
            ]);
        }

        throw $exception;
    }


    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    /**
     * @return int
     */
    public static function checkStatuses_CRON()
    {
        $log_start = microtime(true);

        $total = Author::query()->pluck('id');

        $authors_with = Author::query()->whereHas('products', function ($query) {
            $query->where('status', 1);
        })->pluck('id');

        $authors_without = $total->diff($authors_with);

        Author::query()->whereIn('id', $authors_with)->update(['status' => 1]);
        Author::query()->whereIn('id', $authors_without)->update(['status' => 0]);

        $log_end = microtime(true);
        Log::info('__Check Author Statuses - Total Execution Time: ' . number_format(($log_end - $log_start), 2, ',', '.') . ' sec.');

        return 1;
    }
}
