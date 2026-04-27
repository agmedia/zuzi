<?php

namespace App\Http\Controllers\Back;

use App\Helpers\Country;
use App\Helpers\OrderHelper;
use App\Helpers\ProductHelper;
use App\Http\Controllers\Controller;
use App\Mail\StatusCanceled;
use App\Mail\StatusCompleted;
use App\Mail\StatusPaid;
use App\Mail\StatusReady;
use App\Mail\UnfinishedOrderPromo;
use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Checkout\Shipping\Gls;
use App\Models\Front\Checkout\Shipping\Glsstari;
use App\Models\Front\Checkout\Shipping\HP;
use App\Models\Front\Loyalty;
use App\Services\GiftVoucherService;
use App\Services\UnfinishedOrderPromoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Services\WoltDrive\WoltDriveService;

class OrderController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Order $order)
    {
        $orders   = $order->filter($request)->paginate(config('settings.pagination.back'))->appends(request()->query());
        $statuses = Settings::get('order', 'statuses');
        $promoService = app(UnfinishedOrderPromoService::class);
        $sentPromoActions = collect();

        if ($orders->count()) {
            $promoTitles = $orders->getCollection()
                ->map(fn (Order $listedOrder) => $promoService->titleForOrder($listedOrder))
                ->values();

            $promoActions = Action::query()
                ->whereIn('title', $promoTitles)
                ->where('group', 'total')
                ->get()
                ->keyBy('title');

            $sentPromoActions = $orders->getCollection()
                ->mapWithKeys(function (Order $listedOrder) use ($promoActions, $promoService) {
                    $action = $promoActions->get($promoService->titleForOrder($listedOrder));

                    return $action ? [$listedOrder->id => $action] : [];
                });
        }

        return view('back.order.index', compact('orders', 'statuses', 'sentPromoActions'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('back.order.edit');
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
        $order = new Order();

        $stored = $order->validateRequest($request)->store();

        if ($stored) {
            return redirect()->route('orders.edit', ['order' => $stored])->with(['success' => 'Narudžba je snimljena!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Dogodila se greška prilikom snimanja.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Order $order
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        $statuses = Settings::get('order', 'statuses');

        return view('back.order.show', compact('order', 'statuses'));
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Order $order
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Order $order)
    {
        $countries = Country::list();
        $statuses  = Settings::get('order', 'statuses');
        $shippings = Settings::getList('shipping');
        $payments  = Settings::getList('payment');

        return view('back.order.edit', compact('order', 'countries', 'statuses', 'shippings', 'payments'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Order                    $order
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order)
    {
        $updated = $order->validateRequest($request)->store($order->id);

        if ($updated) {
            return redirect()->route('orders.edit', ['order' => $updated])->with(['success' => 'Narudžba je snimljena!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Dogodila se greška prilikom snimanja.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function api_status_change(Request $request)
    {
        if ($request->has('orders')) {
            $selectedStatus = (int) $request->input('selected');
            $ordersInput = $request->input('orders', []);
            $orders = is_array($ordersInput)
                ? $ordersInput
                : explode(',', trim((string) $ordersInput, '[]'));
            $orders = array_values(array_filter(array_map('intval', $orders)));
            $existingOrders = Order::query()
                ->whereIn('id', $orders)
                ->get(['id', 'order_status_id'])
                ->keyBy('id');

            Order::whereIn('id', $orders)->update([
                'order_status_id' => $selectedStatus
            ]);
            $fullOrders = Order::query()
                ->whereIn('id', $orders)
                ->get()
                ->keyBy('id');

            foreach ($existingOrders as $existingOrder) {
                $order_id = (int) $existingOrder->id;

                if (OrderHelper::shouldReturnStockOnStatusChange((int) $existingOrder->order_status_id, $selectedStatus, $order_id)) {
                    $order_id = (int) $existingOrder->id;
                    ProductHelper::makeAvailable($order_id);
                    Loyalty::cancelPoints($order_id);
                }

                if (in_array($selectedStatus, [config('settings.order.status.paid'), config('settings.order.status.canceled')], true)) {
                    $fullOrder = $fullOrders->get($order_id);

                    if ($fullOrder) {
                        if ($selectedStatus == config('settings.order.status.paid')) {
                            GiftVoucherService::fulfillOrder($fullOrder);
                        }

                        if ($selectedStatus == config('settings.order.status.canceled')) {
                            GiftVoucherService::cancelOrder($fullOrder);
                        }
                    }
                }

                if ($fullOrders->has($order_id)) {
                    $this->dispatchCustomerStatusMail(
                        $fullOrders->get($order_id),
                        $selectedStatus,
                        (int) $existingOrder->order_status_id
                    );
                }

                OrderHistory::store($order_id, new Request([
                    'status' => $selectedStatus,
                    'comment' => '',
                ]));
            }

            return response()->json(['message' => 'Statusi su uspješno promijenjeni..!']);
        }

        if ($request->has('order_id')) {
            if ($request->has('status') && $request->input('status')) {
                $selectedStatus = (int) $request->input('status');
                $order = Order::query()->find($request->input('order_id'));

                if (! $order) {
                    return response()->json(['error' => 'Narudžba nije pronađena.'], 404);
                }

                $previousStatus = (int) $order->order_status_id;

                $order->update([
                    'order_status_id' => $selectedStatus
                ]);

                if (OrderHelper::shouldReturnStockOnStatusChange($previousStatus, $selectedStatus, (int) $order->id)) {
                    ProductHelper::makeAvailable($request->input('order_id'));
                    Loyalty::cancelPoints($request->input('order_id'));
                }

                if ($selectedStatus == config('settings.order.status.paid')) {
                    GiftVoucherService::fulfillOrder($order);

                    dispatch(function () use ($order) {
                        Mail::to($order->payment_email)->send(new StatusPaid($order));
                    });
                }

                if ($selectedStatus == config('settings.order.status.canceled')) {
                    GiftVoucherService::cancelOrder($order);

                    dispatch(function () use ($order) {
                        Mail::to($order->payment_email)->send(new StatusCanceled($order));
                    });
                }

                $this->dispatchCustomerStatusMail($order, $selectedStatus, $previousStatus);
            }

            OrderHistory::store($request->input('order_id'), $request);

            return response()->json(['message' => 'Status je uspješno promijenjen..!']);
        }

        return response()->json(['error' => 'Greška..! Molimo pokušajte ponovo ili kontaktirajte administratora..']);
    }


    private function dispatchCustomerStatusMail(Order $order, int $selectedStatus, int $previousStatus = 0): void
    {
        if (! $order->payment_email || $selectedStatus === $previousStatus) {
            return;
        }

        $notificationType = OrderHelper::resolveCustomerStatusNotificationType(
            $selectedStatus,
            data_get($order->status($selectedStatus), 'title')
        );

        if ($notificationType === 'ready') {
            dispatch(function () use ($order) {
                Mail::to($order->payment_email)->send(new StatusReady($order));
            });
        }

        if ($notificationType === 'completed') {
            dispatch(function () use ($order) {
                Mail::to($order->payment_email)->send(new StatusCompleted($order));
            });
        }
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function api_send_unfinished_promo(Request $request, UnfinishedOrderPromoService $unfinishedOrderPromoService)
    {
        if (! auth()->check()) {
            return response()->json(['error' => 'Niste autorizirani.'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer',
            'discount' => 'required|integer|in:10,15,20',
        ]);

        /** @var Order|null $order */
        $order = Order::query()->find($request->input('order_id'));
        $discount = (int) $request->input('discount');

        if (! $order) {
            return response()->json(['error' => 'Narudžba nije pronađena.'], 404);
        }

        if (! filled($order->payment_email)) {
            return response()->json(['error' => 'Narudžba nema e-mail adresu kupca.'], 422);
        }

        $existingPromoAction = $unfinishedOrderPromoService->findForOrder($order);

        if ($existingPromoAction) {
            return response()->json(['error' => 'Promo mail je već poslan za ovu narudžbu.'], 422);
        }

        try {
            $promoAction = $unfinishedOrderPromoService->issueForOrder($order, $discount);
        } catch (\Throwable $e) {
            Log::error('Failed to issue unfinished order promo coupon.', [
                'order_id' => $order->id,
                'payment_email' => $order->payment_email,
                'discount' => $discount,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Greška..! Generiranje promo koda nije uspjelo.'], 422);
        }

        try {
            Mail::to($order->payment_email)->send(new UnfinishedOrderPromo($order, $promoAction));
        } catch (\Throwable $e) {
            try {
                $promoAction->delete();
            } catch (\Throwable $deleteException) {
                Log::warning('Failed to rollback unfinished order promo coupon after email failure.', [
                    'order_id' => $order->id,
                    'coupon' => $promoAction->coupon ?? null,
                    'error' => $deleteException->getMessage(),
                ]);
            }

            Log::error('Failed to send unfinished order promo email.', [
                'order_id' => $order->id,
                'payment_email' => $order->payment_email,
                'coupon' => $promoAction->coupon ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Greška..! Slanje promo maila nije uspjelo.'], 422);
        }

        try {
            $expiresAt = Carbon::make($promoAction->date_end);
            OrderHistory::store($order->id, new Request([
                'status' => 0,
                'comment' => 'Poslan promo email za nedovrsenu narudzbu. Kod: '
                    . $promoAction->coupon
                    . '. Popust: -'
                    . (int) $promoAction->discount
                    . '%.'
                    . '. Vrijedi do: '
                    . ($expiresAt ? $expiresAt->format('d.m.Y H:i') : ''),
            ]));
        } catch (\Throwable $e) {
            Log::warning('Failed to store unfinished order promo email history.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Promo mail je uspješno poslan.']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function api_send_gls(Request $request)
    {
        $request->validate(['order_id' => 'required']);

        $order = Order::where('id', $request->input('order_id'))->first();

        $gls   = new Gls($order);
        $label = $gls->resolve();

        $var = json_decode($label, true);

        if (isset($var['parcels'][0]['id'])) {
            return response()->json(['message' => 'BOXNOW je uspješno poslan sa ID: ' . $var['parcels'][0]['id']]);
        }

        return response()->json(['error' => 'Greška..! Molimo pokušajte ponovo ili kontaktirajte administratora..']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function api_send_hp_pak(Request $request)
    {
        $request->validate(['order_id' => 'required']);

        $order         = new Order();
        $request_order = $order->newQuery()->where('id', $request->input('order_id'))->first();

        $hp = (new HP($request_order))->createShipmentOrder();

        if ($hp->isSuccessfulResponse()) {
            $comment = 'HP Paketomat je uspješno poslan sa ID: ' . $hp->getPackageBarcode();

            try {
                $hp->setOrderLabelAsPrinted();
                $order->storeHistory($request_order->id, $comment);
                Storage::disk('public')->put($request_order->id . '-hppak.pdf', $hp->getPdfLabel());

            } catch (\Exception $e) { Log::error($e->getMessage()); }

            return response()->json(['message' => $comment]);
        }

        $comment = 'HP Paketomat - ' . $hp->getErrorMessage();

        Log::error($comment);

        return response()->json(['error' => 'Greška..! ' . $comment]);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function api_send_glsstari(Request $request)
    {
        $request->validate(['order_id' => 'required']);

        $order = Order::where('id', $request->input('order_id'))->first();

        $gls   = new Glsstari($order);
        $label = $gls->resolve();

        if (isset($label['ParcelIdList'])) {
            return response()->json(['message' => 'GLS je uspješno poslan sa ID: ' . $label['ParcelIdList'][0]]);
        }

        return response()->json(['error' => 'Greška..! Molimo pokušajte ponovo ili kontaktirajte administratora..']);
    }

    /**
     * Wolt Drive – kreiranje dostave
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function api_send_wolt(Request $request)
    {
        $request->validate(['order_id' => 'required|integer']);

        /** @var \App\Models\Back\Orders\Order $order */
        $order = Order::query()->find($request->input('order_id'));

        if (! $order) {
            return response()->json(['error' => 'Narudžba nije pronađena.'], 404);
        }

        try {
            /** @var WoltDriveService $wolt */
            $wolt = app(WoltDriveService::class);

            // Ako koristiš ENV, dovoljno je:
            $result = $wolt->sendOrderToWolt($order);

            // Ako želiš proslijediti podatke iz fronta umjesto ENV-a:
            // $result = $wolt->sendOrderToWolt(
            //     $order,
            //     $request->input('merchant_id'),
            //     $request->input('venue_id'),
            //     $request->input('merchant_key'),
            // );

            // (Opcionalno) spremi povijest / tracking, ako imaš te kolone:
            $commentParts = [];
            if (!empty($result['delivery_id'])) { $commentParts[] = 'Delivery ID: '.$result['delivery_id']; }
            if (!empty($result['tracking']))    { $commentParts[] = 'Tracking: '.$result['tracking']; }
            if (!empty($result['status']))      { $commentParts[] = 'Status: '.$result['status']; }

            $comment = 'Wolt Drive je uspješno kreiran. '.implode(' | ', $commentParts);

            // Ako tvoj Order model ima helper za spremanje povijesti:
            try {
                OrderHistory::store($order->id, new Request([
                    'comment' => $comment,
                    'notify'  => 0,
                ]));
            } catch (\Throwable $e) {
                Log::warning('WoltDrive: ne mogu spremiti povijest narudžbe', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }

            return response()->json(['message' => $comment, 'result' => $result]);

        } catch (\Throwable $e) {
            Log::error('WoltDrive error', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška..! Slanje na Wolt Drive nije uspjelo. '.$e->getMessage(),
            ], 422);
        }
    }

}
