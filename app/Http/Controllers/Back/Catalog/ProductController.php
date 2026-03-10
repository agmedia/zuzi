<?php

namespace App\Http\Controllers\Back\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductAction;
use App\Models\Back\Catalog\Product\ProductCategory;
use App\Models\Back\Catalog\Product\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Product $product)
    {
        $query = $product->filter($request)->forAdminList();

        $products = $query->paginate(20)->appends($request->query());

        $categories = (new Category())->getList(false);
        /*$authors    = Author::all()->pluck('title', 'id');
        $publishers = Publisher::all()->pluck('title', 'id');*/
        $counts = [];//Product::setCounts($query);

        return view('back.catalog.product.index', compact('products', 'categories'/*, 'authors', 'publishers'*/, 'counts'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $product = new Product();

        $data           = $product->getRelationsData();
        $active_actions = ProductAction::active()->get();

        return view('back.catalog.product.edit', compact('data', 'active_actions'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $product = new Product();

        $stored = $product->validateRequest($request)->create();

        if ($stored) {
            $product->checkSettings()
                    ->storeImages($stored);
            $stored->update([
                'author_id'    => $this->normalizeRelationId($request, 'author_id', 'unknown_author'),
                'publisher_id' => $this->normalizeRelationId($request, 'publisher_id', 'unknown_publisher')
            ]);

            if ($request->boolean('save_and_stay')) {
                return redirect()->route('products.edit', ['product' => $stored])->with(['success' => 'Artikl je uspješno snimljen!']);
            }

            return redirect()->route('products')->with(['success' => 'Artikl je uspješno snimljen!']);
        }

        return redirect()->back()->with(['error' => 'Ops..! Greška prilikom snimanja.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Product $product
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        $data = $product->getRelationsData();

        return view('back.catalog.product.edit', compact('product', 'data'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Product                  $product
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        $updated = $product->validateRequest($request)->edit();

        if ($updated) {
            $product->checkSettings()
                    ->storeImages($updated);
            $updated->update([
                'author_id'    => $this->normalizeRelationId($request, 'author_id', 'unknown_author'),
                'publisher_id' => $this->normalizeRelationId($request, 'publisher_id', 'unknown_publisher')
            ]);

            $product->addHistoryData('change');

            if ($request->boolean('save_and_stay')) {
                return redirect()->route('products.edit', ['product' => $updated])->with(['success' => 'Artikl je uspješno snimljen!']);
            }

            return redirect()->route('products')->with(['success' => 'Artikl je uspješno snimljen!']);
        }

        return redirect()->back()->with(['error' => 'Ops..! Greška prilikom snimanja.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Product $product)
    {
        ProductImage::where('product_id', $product->id)->delete();
        ProductCategory::where('product_id', $product->id)->delete();

        Storage::deleteDirectory(config('filesystems.disks.products.root') . $product->id);

        $destroyed = Product::destroy($product->id);

        if ($destroyed) {
            return redirect()->route('products')->with(['success' => 'Artikl je uspješno snimljen!']);
        }

        return redirect()->back()->with(['error' => 'Ops..! Greška prilikom snimanja.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroyApi(Request $request)
    {
        if ($request->has('id')) {
            $id = $request->input('id');

            ProductImage::where('product_id', $id)->delete();
            ProductCategory::where('product_id', $id)->delete();

            Storage::deleteDirectory(config('filesystems.disks.products.root') . $id);

            $destroyed = Product::destroy($id);

            if ($destroyed) {
                return response()->json(['success' => 200]);
            }
        }

        return response()->json(['error' => 300]);
    }
    /**
     * Pretvara prazan/unknown relation id u 0.
     *
     * @param Request $request
     * @param string  $field
     * @param string  $unknown_config_key
     *
     * @return int
     */
    private function normalizeRelationId(Request $request, string $field, string $unknown_config_key): int
    {
        $id = (int) $request->input($field, 0);
        $unknown_id = (int) config('settings.' . $unknown_config_key);

        if ($id <= 0 || ($unknown_id > 0 && $id === $unknown_id)) {
            return 0;
        }

        return $id;
    }
}
