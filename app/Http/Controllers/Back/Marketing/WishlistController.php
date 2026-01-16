<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $query = Wishlist::query()
            ->with(['product' => function ($q) {
                $q->select('id', 'name', 'sku', 'image', 'url');
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
            ->select('product_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->with(['product' => function ($q) {
                $q->select('id', 'name', 'sku', 'image');
            }])
            ->paginate(20); // broj zapisa po stranici

        $wishlists = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('back.marketing.wishlist.index', compact('wishlists', 'topProducts'));
    }
}
