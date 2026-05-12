<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Country;
use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Controller;
use App\Models\Back\Orders\OrderProduct;
use App\Models\Front\AgCart;
use App\Models\Front\Checkout\Order;
use App\Models\Front\Loyalty;
use App\Services\AccountNoticeService;
use App\Services\ProductRecommendationService;
use App\Models\User;
use App\Models\UserAffiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class CustomerController extends Controller
{

    /**
     * Display account notifications after customer login.
     *
     * @return \Illuminate\Http\Response
     */
    public function notifications(Request $request, AccountNoticeService $account_notice)
    {
        $user = auth()->user();
        $notice = $account_notice->get();
        $notice_valid_until = $account_notice->formattedValidUntil($notice);
        $purchaseRecommendations = $this->purchaseRecommendationsForUser($user);

        UserAffiliate::checkAffiliateName($user);

        return view('front.customer.obavijesti', compact('user', 'notice', 'notice_valid_until', 'purchaseRecommendations'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user      = auth()->user();
        $countries = Country::list();

        CheckoutSession::forgetAddress();
        UserAffiliate::checkAffiliateName($user);

        return view('front.customer.index', compact('user', 'countries'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function orders(Request $request)
    {
        $user   = auth()->user();
        $orderQuery = $this->ordersForUserQuery($user);

        $orders = (clone $orderQuery)
            ->with(['products.product', 'products.real', 'totals'])
            ->latest('created_at')
            ->paginate(10);

        return view('front.customer.moje-narudzbe', compact('user', 'orders'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function loyalty(Request $request)
    {
        $user    = auth()->user();
        $loyalty = Loyalty::where('user_id', $user->id)
            ->latest('created_at')
            ->paginate(10);
        $points  = Loyalty::hasLoyaltyTotal($user->id);

        /*$crypt = encrypt($user->email . '-' . now());
        dd($crypt, decrypt($crypt));*/

        return view('front.customer.loyalty', compact('user', 'loyalty', 'points'));
    }


    /**
     * @param Request $request
     * @param User    $user
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request, User $user)
    {
        $updated = $user->validateFrontRequest($request)->edit();

        if ($updated) {
            return redirect()->route('moj-racun.podaci', ['user' => $updated])->with(['success' => 'Korisnik je uspješno snimljen!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Greška prilikom snimanja.']);
    }

    private function ordersForUserQuery(User $user)
    {
        return Order::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('payment_email', $user->email);
            });
    }

    private function purchaseRecommendationsForUser(User $user)
    {
        $orderQuery = $this->ordersForUserQuery($user);

        $purchasedProductIds = OrderProduct::query()
            ->whereIn('order_id', (clone $orderQuery)
                ->whereNotIn('order_status_id', [5, 7, 8])
                ->select('id'))
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return app(ProductRecommendationService::class)
            ->forProductIds($purchasedProductIds);
    }

}
