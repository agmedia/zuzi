<?php

namespace App\Models\Back\Marketing;

use App\Models\BlogCtaButton;
use App\Models\BlogCtaBlock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * @var array<int, array<string, mixed>>
     */
    protected array $ctaBlocksPayload = [];

    /**
     * @var array<int, string>
     */
    protected array $ctaSelfReferenceWarnings = [];


    /**
     * @return HasMany
     */
    public function ctaBlocks(): HasMany
    {
        return $this->hasMany(BlogCtaBlock::class, 'blog_post_id')
            ->orderBy('sort_order')
            ->orderBy('id');
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
        $request->merge([
            'publish_date' => $this->normalizePublishDateInput($request->input('publish_date')),
            'related_products' => $this->normalizeRelatedProductsInput($request),
            'cta_blocks' => $this->normalizeCtaBlocksInput($request),
        ]);

        $request->validate([
            'title' => 'required',
            'publish_date' => 'nullable|date',
            'related_products' => 'nullable|array',
            'related_products.*' => [
                'integer',
                Rule::exists('products', 'id'),
            ],
            'cta_blocks' => 'nullable|array',
            'cta_blocks.*.title' => 'required|string|max:255',
            'cta_blocks.*.description' => 'nullable|string',
            'cta_blocks.*.sort_order' => 'nullable|integer|min:0',
            'cta_blocks.*.is_active' => 'nullable|boolean',
            'cta_blocks.*.buttons' => 'required|array|min:1',
            'cta_blocks.*.buttons.*.label' => 'required|string|max:255',
            'cta_blocks.*.buttons.*.url' => 'required|string|max:2048',
            'cta_blocks.*.buttons.*.icon' => 'nullable|string|max:32',
            'cta_blocks.*.buttons.*.style' => ['required', Rule::in(BlogCtaButton::STYLES)],
            'cta_blocks.*.buttons.*.sort_order' => 'nullable|integer|min:0',
            'cta_blocks.*.buttons.*.is_active' => 'nullable|boolean',
        ]);

        $this->ctaBlocksPayload = $request->input('cta_blocks', []);
        $this->ctaSelfReferenceWarnings = $this->detectCtaSelfReferenceWarnings(
            $this->ctaBlocksPayload,
            $request->filled('slug') ? Str::slug($request->input('slug')) : Str::slug($request->input('title'))
        );
        $this->request = $request;

        return $this;
    }


    /**
     * Store new category.
     *
     * @return false|Blog
     */
    public function create()
    {
        return DB::transaction(function () {
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
                'status'            => $this->resolveStatus(),
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now()
            ]);

            if (! $id) {
                return false;
            }

            $blog = $this->find($id);

            if (! $blog) {
                return false;
            }

            $this->syncCtaBlocks($blog);

            return $blog->fresh();
        });
    }


    /**
     * @return false|Blog
     */
    public function edit()
    {
        return DB::transaction(function () {
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
                'status'            => $this->resolveStatus(),
                'updated_at'        => Carbon::now()
            ]);

            if (! $id) {
                return false;
            }

            $this->syncCtaBlocks($this);

            return $this->fresh();
        });
    }


    /**
     * @param Blog $blog
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
     * Resolve active status from the admin form.
     */
    private function resolveStatus(): int
    {
        return $this->request->boolean('status') ? 1 : 0;
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
        $selectedProducts = $request->input('related_products');

        if (empty($selectedProducts)) {
            $selectedProducts = $this->decodeRelatedProductsJson($request);
        }

        if (empty($selectedProducts)) {
            $selectedProducts = $request->input('action_list', []);
        }

        return collect($selectedProducts)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }


    /**
     * Normalize CTA blocks and buttons from the admin payload.
     */
    private function normalizeCtaBlocksInput(Request $request): array
    {
        $ctaBlocks = $request->input('cta_blocks', []);

        if (! is_array($ctaBlocks)) {
            return [];
        }

        return collect($ctaBlocks)
            ->filter(fn ($block) => is_array($block))
            ->values()
            ->map(function (array $block, int $blockIndex) {
                $description = trim((string) ($block['description'] ?? ''));
                $buttons = is_array($block['buttons'] ?? null) ? $block['buttons'] : [];

                return [
                    'id' => isset($block['id']) && $block['id'] !== '' ? (int) $block['id'] : null,
                    'title' => trim((string) ($block['title'] ?? '')),
                    'description' => $description !== '' ? $description : null,
                    'sort_order' => $this->normalizeSortOrder($block['sort_order'] ?? null, $blockIndex + 1),
                    'is_active' => $this->normalizeBooleanInput($block['is_active'] ?? 0),
                    'buttons' => collect($buttons)
                        ->filter(fn ($button) => is_array($button))
                        ->values()
                        ->map(function (array $button, int $buttonIndex) {
                            $icon = trim((string) ($button['icon'] ?? ''));

                            return [
                                'id' => isset($button['id']) && $button['id'] !== '' ? (int) $button['id'] : null,
                                'label' => trim((string) ($button['label'] ?? '')),
                                'url' => trim((string) ($button['url'] ?? '')),
                                'icon' => $icon !== '' ? $icon : null,
                                'style' => trim((string) ($button['style'] ?? 'outline')),
                                'sort_order' => $this->normalizeSortOrder($button['sort_order'] ?? null, $buttonIndex + 1),
                                'is_active' => $this->normalizeBooleanInput($button['is_active'] ?? 0),
                            ];
                        })
                        ->all(),
                ];
            })
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


    /**
     * @param Blog $blog
     */
    private function syncCtaBlocks(Blog $blog): void
    {
        $keptBlockIds = [];

        foreach ($this->ctaBlocksPayload as $blockIndex => $blockData) {
            $block = null;

            if (! empty($blockData['id'])) {
                $block = $blog->ctaBlocks()->whereKey((int) $blockData['id'])->first();
            }

            if (! $block) {
                $block = $blog->ctaBlocks()->make();
            }

            $block->fill([
                'title' => $blockData['title'],
                'description' => $blockData['description'] ?? null,
                'sort_order' => $this->normalizeSortOrder($blockData['sort_order'] ?? null, $blockIndex + 1),
                'is_active' => (bool) ($blockData['is_active'] ?? false),
            ]);
            $block->blog_post_id = $blog->id;
            $block->save();

            $keptBlockIds[] = $block->id;

            $this->syncCtaButtons($block, $blockData['buttons'] ?? []);
        }

        if (empty($keptBlockIds)) {
            $blog->ctaBlocks()->delete();

            return;
        }

        $blog->ctaBlocks()->whereNotIn('id', $keptBlockIds)->delete();
    }


    /**
     * @param BlogCtaBlock $block
     * @param array<int, array<string, mixed>> $buttons
     */
    private function syncCtaButtons(BlogCtaBlock $block, array $buttons): void
    {
        $keptButtonIds = [];

        foreach ($buttons as $buttonIndex => $buttonData) {
            $button = null;

            if (! empty($buttonData['id'])) {
                $button = $block->buttons()->whereKey((int) $buttonData['id'])->first();
            }

            if (! $button) {
                $button = $block->buttons()->make();
            }

            $button->fill([
                'label' => $buttonData['label'],
                'url' => $buttonData['url'],
                'icon' => $buttonData['icon'] ?? null,
                'style' => $buttonData['style'] ?? 'outline',
                'sort_order' => $this->normalizeSortOrder($buttonData['sort_order'] ?? null, $buttonIndex + 1),
                'is_active' => (bool) ($buttonData['is_active'] ?? false),
            ]);
            $button->cta_block_id = $block->id;
            $button->save();

            $keptButtonIds[] = $button->id;
        }

        if (empty($keptButtonIds)) {
            $block->buttons()->delete();

            return;
        }

        $block->buttons()->whereNotIn('id', $keptButtonIds)->delete();
    }


    /**
     * @param array<int, array<string, mixed>> $ctaBlocks
     * @param string $slug
     *
     * @return array<int, string>
     */
    private function detectCtaSelfReferenceWarnings(array $ctaBlocks, string $slug): array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return [];
        }

        $currentBlogPath = $this->normalizeRelativeUrl('/blog/' . ltrim($slug, '/'));

        return collect($ctaBlocks)
            ->flatMap(fn (array $block) => $block['buttons'] ?? [])
            ->filter(function (array $button) use ($currentBlogPath) {
                return $this->normalizeRelativeUrl($button['url'] ?? null) === $currentBlogPath;
            })
            ->map(fn (array $button) => $button['label'] ?? 'Nepoznati CTA button')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }


    /**
     * Normalize internal URLs so self-references can be detected reliably.
     */
    private function normalizeRelativeUrl($url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = $url;
        }

        $path = '/' . ltrim($path, '/');

        return rtrim($path, '/') ?: '/';
    }


    /**
     * Normalize checkbox-like values from nested form payloads.
     */
    private function normalizeBooleanInput($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }


    /**
     * Normalize user-entered sort order values.
     */
    private function normalizeSortOrder($value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return max(0, (int) $value);
    }


    /**
     * Surface CTA self-link warnings after save without blocking persistence.
     */
    public function ctaWarningMessage(): ?string
    {
        if (empty($this->ctaSelfReferenceWarnings)) {
            return null;
        }

        return 'CTA buttoni vode na isti blog članak: ' . implode(', ', $this->ctaSelfReferenceWarnings) . '.';
    }
}
