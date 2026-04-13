<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Controller;
use App\Models\Front\AgCart;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Services\GiftVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GiftVoucherController extends Controller
{
    public function create()
    {
        $amounts = GiftVoucherService::availableAmounts();

        return view('front.gift-vouchers.create', compact('amounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => [
                'required',
                'integer',
                'min:10',
                'max:300',
                function ($attribute, $value, $fail) {
                    if (((int) $value) % 10 !== 0) {
                        $fail('Vrijednost poklon bona mora biti u koracima od 10 €.');
                    }
                },
            ],
            'recipient_name' => ['nullable', 'string', 'max:191'],
            'recipient_email' => ['required', 'email', 'max:191'],
            'sender_name' => ['nullable', 'string', 'max:191'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $cart = $this->shoppingCart();
        $cartData = $cart->get();

        if (GiftVoucherService::cartHasRegularItems($cartData)) {
            return back()
                ->withInput()
                ->with(['error' => 'Poklon bon se kupuje zasebno. Dovršite postojeću kupnju ili ispraznite košaricu prije dodavanja bona.']);
        }

        foreach ($cartData['items'] ?? [] as $item) {
            if (GiftVoucherService::isGiftVoucherItem($item)) {
                $cart->remove($item->id);
            }
        }

        session()->forget(config('session.cart') . '_coupon');
        session()->forget(config('session.cart') . '_loyalty');
        CheckoutSession::forgetCheckout();

        $response = $cart->add(GiftVoucherService::buildCartItemRequest($validated));

        if (isset($response['error'])) {
            return back()->withInput()->with(['error' => $response['error']]);
        }

        $cart->resolveDB($response);

        return redirect()
            ->route('kosarica')
            ->with(['success' => 'Poklon bon je dodan u košaricu. Ovu kupnju moguće je dovršiti isključivo kartičnim plaćanjem.']);
    }

    public function guide(?string $recipient = null)
    {
        $recipients = collect($this->giftGuideRecipients())->values();
        $activeRecipient = $recipients->firstWhere('slug', $recipient)
            ?: $recipients->firstWhere('slug', 'djevojke')
            ?: $recipients->first();

        $categories = $this->resolveGiftGuideCategories($activeRecipient);
        $ids = $this->resolveGiftGuideProductIds($categories);

        if ($ids->isEmpty()) {
            $ids = $this->resolveGiftGuideFallbackIds();
        }

        $giftGuide = [
            'title' => 'Tražiš poklon?',
            'lead' => 'Odaberi za koga kupuješ i Zuzi ti odmah slaže preporuke iz pravih kategorija.',
            'body' => 'Kombinirali smo najtraženije žanrove i teme za žene, muškarce, djevojke, dečke i djecu kako bi odabir poklona bio brz, lijep i bez lutanja.',
            'seo_title' => 'Tražiš poklon? ' . ($activeRecipient['heading'] ?? $activeRecipient['title']),
            'seo_description' => 'Odaberi za koga tražiš poklon i pregledaj preporučene knjige, gift program i tematske prijedloge u ZUZI Shopu.',
            'seo_image' => 'media/img/category/gift-program.png',
        ];

        $categoryLinks = $categories
            ->map(function (Category $category) {
                $group = Str::slug((string) $category->group);

                if ((int) $category->parent_id > 0) {
                    $parent = $category->relationLoaded('parent') ? $category->parent : $category->parent()->first();

                    if ($parent) {
                        return [
                            'title' => $category->title,
                            'url' => route('catalog.route', ['group' => $group, 'cat' => $parent, 'subcat' => $category]),
                        ];
                    }
                }

                return [
                    'title' => $category->title,
                    'url' => route('catalog.route', ['group' => $group, 'cat' => $category]),
                ];
            })
            ->values();

        return view('front.gift-guides.index', compact('giftGuide', 'recipients', 'activeRecipient', 'categoryLinks', 'ids'));
    }

    private function shoppingCart(): AgCart
    {
        $key = config('session.cart');

        if (session()->has($key)) {
            return new AgCart(session($key));
        }

        $cartId = Str::random(8);
        session([$key => $cartId]);

        return new AgCart($cartId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function giftGuideRecipients(): array
    {
        return [
            [
                'slug' => 'zene',
                'title' => 'Pokloni za žene',
                'heading' => 'Pokloni za žene',
                'description' => 'Romantični, inspirativni i lifestyle naslovi za poklon koji djeluje pažljivo odabran.',
                'meta' => 'Ljubići, psihologija i biografije',
                'icon' => 'heels',
                'category_slugs' => ['ljubici', 'psihologija', 'autobiografije-i-biografije', 'kuharice', 'duhovne-knjige'],
                'category_keywords' => ['Ljubići', 'Psihologija', 'Biografije', 'Kuharice', 'Duhovne knjige'],
            ],
            [
                'slug' => 'muskarce',
                'title' => 'Pokloni za muškarce',
                'heading' => 'Pokloni za muškarce',
                'description' => 'Napeti, snažni i faktografski naslovi za poklon koji lako pogađa interes.',
                'meta' => 'Krimići, povijest, SF i stripovi',
                'icon' => 'mustache',
                'category_slugs' => ['krimici', 'povijest', 'sf', 'stripovi', 'publicistika'],
                'category_keywords' => ['Krimići', 'Povijest', 'SF', 'Stripovi', 'Publicistika'],
            ],
            [
                'slug' => 'djevojke',
                'title' => 'Pokloni za djevojke',
                'heading' => 'Pokloni za djevojke',
                'description' => 'Popularni YA, fantasy i feel-good izbor za poklon koji se odmah poželi otvoriti.',
                'meta' => 'Literatura za mlade, fantasy i ljubići',
                'icon' => 'tiara',
                'category_slugs' => ['literatura-za-mlade', 'fantasy', 'ljubici', 'psihologija'],
                'category_keywords' => ['Literatura za mlade', 'Fantasy', 'Ljubići', 'Psihologija'],
            ],
            [
                'slug' => 'decke',
                'title' => 'Pokloni za dečke',
                'heading' => 'Pokloni za dečke',
                'description' => 'Avantura, misterij i mašta u izboru koji brzo pretvara poklon u favorit.',
                'meta' => 'Fantasy, SF, krimići i stripovi',
                'icon' => 'cap',
                'category_slugs' => ['fantasy', 'sf', 'krimici', 'stripovi', 'literatura-za-mlade'],
                'category_keywords' => ['Fantasy', 'SF', 'Krimići', 'Stripovi', 'Literatura za mlade'],
            ],
            [
                'slug' => 'djecu',
                'title' => 'Pokloni za djecu',
                'heading' => 'Pokloni za djecu',
                'description' => 'Slikovnice, bojanke i dječji favoriti za vesele, šarene i sigurne poklone.',
                'meta' => 'Dječje knjige, slikovnice i bojanke',
                'icon' => 'blocks',
                'category_slugs' => ['djecje-knjige', 'slikovnice', 'bojanke', 'literatura-za-mlade'],
                'category_keywords' => ['Dječje knjige', 'Slikovnice', 'Bojanke', 'Literatura za mlade'],
            ],
        ];
    }

    private function resolveGiftGuideCategories(array $recipient): Collection
    {
        $configuredSlugs = collect($recipient['category_slugs'] ?? [])->filter()->values();

        $categories = Category::query()
            ->active()
            ->with('parent')
            ->whereIn('slug', $configuredSlugs)
            ->get(['id', 'parent_id', 'title', 'slug', 'group']);

        if ($categories->isEmpty()) {
            $keywords = collect($recipient['category_keywords'] ?? [])->filter()->values();

            $categories = Category::query()
                ->active()
                ->with('parent')
                ->where(function ($query) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $query->orWhere('title', 'like', '%' . $keyword . '%')
                            ->orWhere('slug', 'like', '%' . Str::slug($keyword) . '%');
                    }
                })
                ->limit(6)
                ->get(['id', 'parent_id', 'title', 'slug', 'group']);
        }

        if ($categories->isEmpty()) {
            return collect();
        }

        $orderedSlugs = $configuredSlugs->flip();

        return $categories
            ->sortBy(function (Category $category) use ($orderedSlugs) {
                return $orderedSlugs->get($category->slug, PHP_INT_MAX);
            })
            ->values();
    }

    private function resolveGiftGuideProductIds(Collection $categories): Collection
    {
        if ($categories->isEmpty()) {
            return collect();
        }

        $categoryIds = $categories->pluck('id')->filter()->values();

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('category_id', $categoryIds);
            })
            ->orderByDesc('viewed')
            ->orderByDesc('created_at')
            ->limit(180)
            ->pluck('id')
            ->values();
    }

    private function resolveGiftGuideFallbackIds(): Collection
    {
        return Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->orderByDesc('viewed')
            ->orderByDesc('created_at')
            ->limit(180)
            ->pluck('id')
            ->values();
    }
}
