<?php

namespace App\Models\Back\Marketing;

use App\Helpers\Helper;
use App\Models\Back\Catalog\Author;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductCategory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Action extends Model
{

    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'product_actions';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var Request
     */
    protected $request;


    /**
     * Validate new action Request.
     *
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        $request->validate([
            'title'    => 'required',
            'type'     => 'required',
            'group'    => 'required',
            'discount' => 'required'
        ]);

        $this->request = $request;

        if ($this->listRequired()) {
            $request->validate([
                'action_list' => 'required'
            ]);
        }

        return $this;
    }


    /**
     * Store new category.
     *
     * @return false
     */
    public function create()
    {
        $links = collect(['all']);

        if ($this->request->action_list) {
            $links = collect($this->request->action_list);
        }

        $status = (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0;
        $start  = $this->request->date_start ? Carbon::make($this->request->date_start) : null;
        $end    = $this->request->date_end ? Carbon::make($this->request->date_end) : null;

        $id = $this->insertGetId([
            'title'      => $this->request->title,
            'type'       => $this->request->type,
            'discount'   => $this->request->discount,
            'group'      => $this->request->group,
            'links'      => $links->flatten()->toJson(),
            'date_start' => $start,
            'date_end'   => $end,
            'status'     => $status,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        if ($id) {
            if ($status) {
                $this->updateProducts($this->resolveTarget($links), $id, $start, $end);
            }

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
        $links = collect(['all']);

        if ($this->request->action_list) {
            $links = collect($this->request->action_list);
        }

        $status = (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0;
        $start  = $this->request->date_start ? Carbon::make($this->request->date_start) : null;
        $end    = $this->request->date_end ? Carbon::make($this->request->date_end) : null;

        $updated = $this->update([
            'title'      => $this->request->title,
            'type'       => $this->request->type,
            'discount'   => $this->request->discount,
            'group'      => $this->request->group,
            'links'      => $links->flatten()->toJson(),
            'date_start' => $start,
            'date_end'   => $end,
            'status'     => $status,
            'updated_at' => Carbon::now()
        ]);

        if ($updated) {
            $this->truncateProducts();

            if ($status) {
                $ids = $this->resolveTarget($links);
                $this->updateProducts($ids, $this->id, $start, $end);
            }

            return $this;
        }

        return false;
    }


    /**
     * @return bool
     */
    public function listRequired(): bool
    {
        if ($this->request->group == 'all') {
            return false;
        }

        return true;
    }


    /**
     * @param $links
     *
     * @return mixed
     */
    private function resolveTarget($links)
    {
        if ($this->request->group == 'product') {
            return $links;
        }

        if ($this->request->group == 'category') {
            return ProductCategory::whereIn('category_id', $links)->pluck('product_id')->unique();
        }

        if ($this->request->group == 'author') {
            return Product::whereIn('author_id', $links)->pluck('id')->unique();
        }

        if ($this->request->group == 'publisher') {
            return Product::whereIn('publisher_id', $links)->pluck('id')->unique();
        }

        if ($this->request->group == 'all') {
            return 'all';
        }
    }


    /**
     * @param     $ids
     * @param int $id
     * @param     $start
     * @param     $end
     */
    private function updateProducts($ids, int $id, $start, $end): void
    {
        $query = [];

        if ($ids == 'all') {
            $products = Product::pluck('price', 'id');
        } else {
            $products = Product::whereIn('id', $ids)->pluck('price', 'id');
        }

        foreach ($products->all() as $k_id => $price) {
            $query[] = [
                'product_id' => $k_id,
                'special'    => Helper::calculateDiscountPrice($price, $this->request->discount)
            ];
        }

        $start = $start ?: 'null';
        $end = $end ?: 'null';

        DB::table('temp_table')->truncate();

        foreach (array_chunk($query,500) as $chunk) {
            DB::table('temp_table')->insert($chunk);
        }

        DB::select(DB::raw("UPDATE products p INNER JOIN temp_table tt ON p.id = tt.product_id SET p.special = tt.special, p.action_id = " . $id . ", p.special_from = '" . $start . "', p.special_to = '" . $end . "';"));

        DB::table('temp_table')->truncate();
    }


    /**
     * @return mixed
     */
    public function truncateProducts()
    {
        return Product::where('action_id', $this->id)->update([
            'action_id'    => 0,
            'special'      => null,
            'special_from' => null,
            'special_to'   => null,
        ]);
    }
}
