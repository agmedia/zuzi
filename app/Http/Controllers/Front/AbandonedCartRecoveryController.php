<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Controller;
use App\Models\Back\Orders\Order;
use App\Models\Front\AgCart;
use App\Models\Front\Catalog\Product;
use App\Services\AbandonedCartReminderService;
use App\Services\CouponUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AbandonedCartRecoveryController extends Controller
{
    public function __invoke(Request $request, Order $order, AbandonedCartReminderService $reminders): RedirectResponse
    {
        $order->refresh();
        $this->ensureCurrentCustomerOwnsOrder($request, $order);

        if (! $reminders->canRecover($order)) {
            return redirect()->route('kosarica')
                ->with('info', 'Ova narudžba više nije dostupna za nastavak kupnje.');
        }

        $order->loadMissing('products');
        $cartKey = (string) config('session.cart', 'zuzi_cart');
        $cartId = session($cartKey);

        if (! is_string($cartId) || $cartId === '') {
            $cartId = Str::random(32);
            session([$cartKey => $cartId]);
        }

        $cart = new AgCart($cartId);
        $existingIds = $cart->getCartItems(true)->pluck('id')->map(fn ($id) => (string) $id);
        $selectedProducts = $order->products
            ->filter(fn ($item) => (int) $item->product_id > 0 && (int) $item->quantity > 0)
            ->groupBy('product_id');
        $available = Product::query()
            ->active()
            ->whereIn('id', $selectedProducts->keys()->all())
            ->where('quantity', '>', 0)
            ->get()
            ->keyBy('id');
        $restored = 0;
        $alreadyPresent = 0;

        foreach ($selectedProducts as $productId => $items) {
            if ($existingIds->contains((string) $productId)) {
                $alreadyPresent++;
                continue;
            }

            $product = $available->get($productId);
            if (! $product) {
                continue;
            }

            $quantity = min((int) $items->sum('quantity'), (int) $product->quantity);
            $result = $cart->add(['item' => [
                'id' => (int) $product->id,
                'quantity' => $quantity,
            ]]);

            if (! isset($result['error'])) {
                $restored++;
                $existingIds->push((string) $productId);
            }
        }

        if (($restored + $alreadyPresent) < 1) {
            return redirect()->route('kosarica')
                ->with('error', 'Odabrane knjige trenutačno nisu dostupne.');
        }

        // Keep an existing checkout and its address intact. A fresh checkout
        // creates its own order, so this link can never reopen an old purchase.
        if (! CheckoutSession::hasOrder() && ! CheckoutSession::hasAddress()) {
            CheckoutSession::setAddress([
                'fname' => (string) $order->payment_fname,
                'lname' => (string) $order->payment_lname,
                'email' => (string) $order->payment_email,
                'phone' => (string) $order->payment_phone,
                'address' => (string) $order->payment_address,
                'city' => (string) $order->payment_city,
                'zip' => (string) $order->payment_zip,
                'company' => (string) ($order->company ?? ''),
                'oib' => (string) ($order->oib ?? ''),
                'state' => (string) ($order->payment_state ?: 'Croatia'),
            ]);
        }

        return redirect()->route('kosarica')
            ->with('success', 'Dostupne knjige iz tvog odabira nalaze se u košarici. Provjeri količine i dovrši kupnju.');
    }

    private function ensureCurrentCustomerOwnsOrder(Request $request, Order $order): void
    {
        $user = $request->user();

        if (! $user) {
            return;
        }

        $emails = app(CouponUsageService::class);
        $customerEmail = $emails->normalizeEmail($user->email);
        $orderEmail = $emails->normalizeEmail($order->payment_email);
        $ownsById = (int) $order->user_id > 0 && (int) $order->user_id === (int) $user->id;
        $ownsByEmail = $customerEmail !== '' && $customerEmail === $orderEmail;

        abort_unless($ownsById || $ownsByEmail, 403);
    }
}
