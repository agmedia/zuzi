<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Product\Product as AdminProduct;
use App\Models\Front\Catalog\Product as FrontProduct;
use App\Models\Back\Marketing\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $query = Wishlist::query()
            ->with(['product' => function ($q) {
                $q->select('id', 'name', 'sku', 'image', 'url', 'quantity', 'status', 'price', 'special', 'special_from', 'special_to');
            }]);

        $stockFilter = $request->input('stock');

        // Filtriranje po nazivu ili šifri proizvoda
        if ($search = $request->input('search')) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $query
            ->when($stockFilter === 'in-stock', function ($wishlistQuery) {
                $wishlistQuery->whereHas('product', function ($productQuery) {
                    $productQuery->where('quantity', '!=', 0);
                });
            })
            ->when($stockFilter === 'out-of-stock', function ($wishlistQuery) {
                $wishlistQuery->whereHas('product', function ($productQuery) {
                    $productQuery->where('quantity', 0);
                });
            });

        // ako se želi prikazati broj predbilježbi po artiklu
        $topProducts = Wishlist::query()
            ->active()
            ->unsent()
            ->select('product_id')
            ->selectRaw('COUNT(*) as total')
            ->when($stockFilter === 'in-stock', function ($wishlistQuery) {
                $wishlistQuery->whereHas('product', function ($productQuery) {
                    $productQuery->where('quantity', '!=', 0);
                });
            })
            ->when($stockFilter === 'out-of-stock', function ($wishlistQuery) {
                $wishlistQuery->whereHas('product', function ($productQuery) {
                    $productQuery->where('quantity', 0);
                });
            })
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->with(['product' => function ($q) {
                $q->select('id', 'name', 'sku', 'image', 'url', 'quantity', 'status', 'price', 'special', 'special_from', 'special_to');
            }])
            ->paginate(20); // broj zapisa po stranici

        $wishlists = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('back.marketing.wishlist.index', compact('wishlists', 'topProducts', 'stockFilter'));
    }


    public function showProduct(AdminProduct $product)
    {
        $baseQuery = Wishlist::query()->where('product_id', $product->id);

        $pendingCount = (clone $baseQuery)->active()->unsent()->count();
        $sentCount = (clone $baseQuery)->sent()->count();
        $totalCount = (clone $baseQuery)->count();

        $entries = (clone $baseQuery)
            ->with(['user.details'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('back.marketing.wishlist.product', compact('product', 'entries', 'pendingCount', 'sentCount', 'totalCount'));
    }


    public function sendProduct(AdminProduct $product)
    {
        $frontProduct = $this->resolveFrontProduct($product->id);

        if (! $frontProduct) {
            return back()->with(['error' => 'Artikl nije pronađen.']);
        }

        if ((int) $frontProduct->quantity === 0) {
            return back()->with(['error' => 'Artikl nije na stanju pa obavijesti nisu poslane.']);
        }

        $sentCount = Wishlist::sendPendingNotificationsForProduct($frontProduct);

        if ($sentCount < 1) {
            return back()->with(['error' => 'Za ovaj artikl nema aktivnih prijava za slanje.']);
        }

        return back()->with([
            'success' => 'Poslano je ' . $sentCount . ' wishlist obavijesti za artikl "' . $frontProduct->name . '".',
        ]);
    }


    public function sendWishlist(Wishlist $wishlist)
    {
        $frontProduct = $this->resolveFrontProduct((int) $wishlist->product_id);

        if (! $frontProduct) {
            return back()->with(['error' => 'Artikl nije pronađen.']);
        }

        if ((int) $frontProduct->quantity === 0) {
            return back()->with(['error' => 'Artikl nije na stanju pa obavijest nije poslana.']);
        }

        if (! Wishlist::sendNotificationForEntry($wishlist, $frontProduct)) {
            return back()->with(['error' => 'Ova prijava je već obrađena ili više nije aktivna.']);
        }

        return back()->with([
            'success' => 'Poslana je wishlist obavijest korisniku ' . $wishlist->email . ' za artikl "' . $frontProduct->name . '".',
        ]);
    }


    private function resolveFrontProduct(int $productId): ?FrontProduct
    {
        return FrontProduct::query()
            ->select('id', 'name', 'url', 'image', 'price', 'special', 'quantity', 'status', 'special_from', 'special_to')
            ->find($productId);
    }
}
