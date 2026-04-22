<?php

namespace App\Models\Back\Marketing;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Facades\Image;

class Blog extends Model
{

    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'pages';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'publish_date' => 'datetime',
        'related_products' => 'array',
    ];

    /**
     * @var Request
     */
    protected $request;


    /**
     * Validate new category Request.
     *
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        $request->merge([
            'publish_date' => $this->normalizePublishDateInput($request->input('publish_date')),
            'related_products' => $this->normalizeRelatedProductsInput($request),
        ]);

        $request->validate([
            'title' => 'required',
            'publish_date' => 'nullable|date',
            'related_products' => 'nullable|array',
            'related_products.*' => [
                'integer',
                Rule::exists('products', 'id'),
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
        $id = $this->insertGetId([
            'category_id'       => null,
            'group'             => 'blog',
            'title'             => $this->request->title,
            'short_description' => $this->request->short_description,
            'description'       => $this->request->description,
            'meta_title'        => $this->request->meta_title,
            'meta_description'  => $this->request->meta_description,
            'slug'              => isset($this->request->slug) ? Str::slug($this->request->slug) : Str::slug($this->request->title),
            'keywords'          => null,
            'publish_date'      => $this->resolvePublishDate(),
            'keywords'          => false,
            'related_products'  => $this->encodeRelatedProducts(),
            'status'            => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now()
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
        $id = $this->update([
            'category_id'       => null,
            'group'             => 'blog',
            'title'             => $this->request->title,
            'short_description' => $this->request->short_description,
            'description'       => $this->request->description,
            'meta_title'        => $this->request->meta_title,
            'meta_description'  => $this->request->meta_description,
            'slug'              => isset($this->request->slug) ? Str::slug($this->request->slug) : Str::slug($this->request->title),
            'keywords'          => null,
            'publish_date'      => $this->resolvePublishDate(),
            'keywords'          => false,
            'related_products'  => $this->resolveRelatedProducts(),
            'status'            => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'updated_at'        => Carbon::now()
        ]);

        if ($id) {
            return $this->find($this->id);
        }

        return false;
    }


    /**
     * @param Category $category
     *
     * @return bool
     */
    public function resolveImage(Blog $blog)
    {
        if ($this->request->hasFile('image')) {
            $img = Image::make($this->request->image);
            $str = $blog->id . '/' . Str::slug($blog->title) . '-' . time() . '.';

            $path = $str . 'jpg';
            Storage::disk('blog')->put($path, $img->encode('jpg'));

            $path_webp = $str . 'webp';
            Storage::disk('blog')->put($path_webp, $img->encode('webp'));

            return $blog->update([
                'image' => config('filesystems.disks.blog.url') . $path
            ]);
        }

        return false;
    }


    /**
     * Resolve the normalized publish date.
     */
    private function resolvePublishDate(): ?Carbon
    {
        $publishDate = $this->request->input('publish_date');

        return $publishDate ? Carbon::parse($publishDate) : null;
    }


    /**
     * Normalize selected related product ids before storage.
     */
    private function resolveRelatedProducts(): array
    {
        return collect($this->request->input('related_products', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }


    /**
     * Encode selected related products for raw insert queries.
     */
    private function encodeRelatedProducts(): ?string
    {
        $ids = $this->resolveRelatedProducts();

        return ! empty($ids) ? json_encode($ids) : null;
    }


    /**
     * Support both the new related_products[] field and legacy action_list[] payloads.
     */
    private function normalizeRelatedProductsInput(Request $request): array
    {
        return collect($request->input('related_products', $this->decodeRelatedProductsJson($request) ?: $request->input('action_list', [])))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }


    /**
     * Normalize publish_date from admin form formats into a database-friendly value.
     */
    private function normalizePublishDateInput($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['d.m.Y H:i', 'd.m.Y', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->format('Y-m-d H:i:s');
                }
            } catch (\Throwable $exception) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            return $value;
        }
    }


    /**
     * Read selected ids from the hidden JSON field as a fallback for Select2.
     */
    private function decodeRelatedProductsJson(Request $request): array
    {
        $payload = $request->input('related_products_json');

        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
