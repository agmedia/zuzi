<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Product\Product as AdminProduct;
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

        // Filtriranje po nazivu ili šifri proizvoda
        if ($search = $request->input('search')) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // ako se želi prikazati broj predbilježbi po artiklu
        $topProducts = Wishlist::query()
            ->active()
            ->unsent()
            ->select('product_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->with(['product' => function ($q) {
                $q->select('id', 'name', 'sku', 'image', 'url', 'quantity', 'status', 'price', 'special', 'special_from', 'special_to');
            }])
            ->paginate(20); // broj zapisa po stranici

        $wishlists = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('back.marketing.wishlist.index', compact('wishlists', 'topProducts'));
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
}
