<?php

namespace App\Services;

use App\Mail\UnfinishedOrderReminder;
use App\Models\AbandonedCartReminder;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class AbandonedCartReminderService
{
    public function isAvailable(): bool
    {
        return Schema::hasTable('abandoned_cart_reminders')
            && Schema::hasColumn('orders', 'unfinished_at')
            && Schema::hasColumn('orders', 'checkout_processed_at');
    }

    public function candidatesForSequence(int $sequence, int $limit): Collection
    {
        $candidates = collect();
        $startsAt = $this->startsAt();

        if (! config('abandoned_cart.enabled', true) || ! $this->isAvailable()
            || ! $startsAt || ! in_array($sequence, [1, 2], true)) {
            return $candidates;
        }

        $query = Order::query()
            ->where('order_status_id', (int) config('settings.order.status.unfinished', 8))
            ->whereNull('checkout_processed_at')
            ->where('created_at', '>=', $startsAt)
            ->whereRaw('COALESCE(unfinished_at, created_at) >= ?', [now()->subDays((int) config('abandoned_cart.max_age_days', 3))])
            ->whereRaw('COALESCE(unfinished_at, created_at) <= ?', [now()->subMinutes($this->delay($sequence))])
            ->whereNotNull('payment_email')
            ->whereRaw("TRIM(payment_email) <> ''")
            ->whereHas('products')
            ->whereDoesntHave('transactions', fn (Builder $q) => $q->where('success', 1))
            // Any reserved attempt counts, including uncertain SMTP failures.
            ->whereDoesntHave('abandonedCartReminders', fn (Builder $q) => $q->where('sequence', $sequence));

        if ($sequence === 2) {
            $query->whereHas('abandonedCartReminders', function (Builder $q) {
                $q->where('sequence', 1)->whereNotNull('sent_at')
                    ->where('sent_at', '<=', now()->subMinutes($this->minimumGap()));
            });
        }

        $query->with(['products.product', 'totals'])->chunkById(100, function ($orders) use ($candidates, $sequence, $limit) {
            foreach ($orders as $order) {
                if ($this->eligibilityError($order, true) === null && $this->nextSequence($order) === $sequence) {
                    $candidates->push($order);
                    if ($candidates->count() >= max(1, $limit)) {
                        return false;
                    }
                }
            }
        });

        return $candidates;
    }

    public function scheduledFor(Order $order, int $sequence): Carbon
    {
        $scheduled = Carbon::parse($order->unfinished_at ?: $order->created_at)->addMinutes($this->delay($sequence));
        if ($sequence === 2) {
            $first = AbandonedCartReminder::query()->where('order_id', $order->id)->where('sequence', 1)->first();
            if ($first && $first->sent_at) {
                $scheduled = $scheduled->max(Carbon::parse($first->sent_at)->addMinutes($this->minimumGap()));
            }
        }

        return $scheduled;
    }

    public function nextSequence(Order $order): ?int
    {
        $attempts = AbandonedCartReminder::query()->where('order_id', $order->id)->get()->keyBy('sequence');
        if (! $attempts->has(1)) {
            return 1;
        }

        return $attempts->get(1)->sent_at && ! $attempts->has(2) ? 2 : null;
    }

    public function send(Order $order, int $sequence, string $source = AbandonedCartReminder::SOURCE_AUTOMATIC): AbandonedCartReminder
    {
        if (! in_array($source, [AbandonedCartReminder::SOURCE_AUTOMATIC, AbandonedCartReminder::SOURCE_MANUAL], true)) {
            throw new RuntimeException('Nepoznat izvor podsjetnika.');
        }

        return $this->withRecipientLock($order, function (Order $fresh) use ($sequence, $source) {
            $automatic = $source === AbandonedCartReminder::SOURCE_AUTOMATIC;
            if (! config('abandoned_cart.enabled', true) || ! $this->isAvailable()) {
                throw new RuntimeException('Podsjetnici trenutačno nisu dostupni.');
            }
            if ($error = $this->eligibilityError($fresh, $automatic)) {
                throw new RuntimeException($error);
            }
            if (! in_array($sequence, [1, 2], true) || $this->nextSequence($fresh) !== $sequence) {
                throw new RuntimeException('Podsjetnik je već poslan ili je slanje već pokušano.');
            }
            if (($automatic || $sequence === 2) && now()->lt($this->scheduledFor($fresh, $sequence))) {
                throw new RuntimeException('Vrijeme za ovaj podsjetnik još nije nastupilo.');
            }

            // Commit the unique reservation BEFORE SMTP. Never erase it on failure:
            // the mail server may have accepted a message before a connection error.
            $claim = $this->reserve($fresh, $sequence, $source);
            $recipientUserId = (int) $fresh->user_id;
            try {
                DB::transaction(function () use ($fresh, $automatic, $claim, $recipientUserId, $sequence) {
                    // Checkout updates this same row, so its completion cannot
                    // pass our final check while a message is being submitted.
                    $current = Order::query()->lockForUpdate()->findOrFail($fresh->id);
                    if ($this->email($current) !== $claim->recipient_email || (int) $current->user_id !== $recipientUserId) {
                        throw new RuntimeException('Podaci kupca promijenjeni su prije slanja podsjetnika.');
                    }
                    $current->load(['products.product', 'totals']);
                    if ($error = $this->eligibilityError($current, $automatic)) {
                        throw new RuntimeException($error);
                    }
                    Mail::to($claim->recipient_email)->send(new UnfinishedOrderReminder($current, $this->recoveryUrl($current), $sequence));
                    $claim->forceFill(['sent_at' => now()])->save();
                });
            } catch (\Throwable $exception) {
                $this->recordError($claim, $exception->getMessage());
                throw $exception;
            }

            try {
                OrderHistory::query()->create([
                    'order_id' => $fresh->id,
                    'user_id' => auth()->id() ?: 0,
                    'status' => 0,
                    'comment' => $sequence === 1 ? UnfinishedOrderPromoService::REMINDER_HISTORY_COMMENT
                        : 'Poslan drugi automatski podsjetnik za nedovrsenu narudzbu.',
                ]);
            } catch (\Throwable $exception) {
                $this->logWarning('Reminder delivered but history could not be saved.', $fresh->id, $exception);
            }

            return $claim;
        });
    }

    /** Coordinate the existing manual HVALA promotion with automatic reminders. */
    public function sendPromotion(Order $order, callable $send)
    {
        return $this->withRecipientLock($order, function (Order $fresh) use ($send) {
            if ($error = $this->eligibilityError($fresh, false, false)) {
                throw new RuntimeException($error);
            }
            if (AbandonedCartReminder::query()->where('order_id', $fresh->id)->exists()) {
                throw new RuntimeException('Za ovu narudžbu podsjetnik je već poslan ili je slanje već pokušano.');
            }
            $claim = $this->reserve($fresh, 1, AbandonedCartReminder::SOURCE_MANUAL);
            try {
                return DB::transaction(function () use ($fresh, $send, $claim) {
                    $current = Order::query()->lockForUpdate()->findOrFail($fresh->id);
                    if ($this->email($current) !== $claim->recipient_email || (int) $current->user_id !== (int) $fresh->user_id) {
                        throw new RuntimeException('Podaci kupca promijenjeni su prije slanja podsjetnika.');
                    }
                    if ($error = $this->eligibilityError($current, false, false)) {
                        throw new RuntimeException($error);
                    }
                    $response = $send($current);
                    if ($response->getStatusCode() < 300) {
                        $claim->forceFill(['sent_at' => now()])->save();
                    } else {
                        $this->recordError($claim, 'Ručno slanje promo poruke nije potvrđeno.');
                    }
                    return $response;
                });
            } catch (\Throwable $exception) {
                $this->recordError($claim, $exception->getMessage());
                throw $exception;
            }
        });
    }

    public function canRecover(Order $order): bool
    {
        $fresh = Order::query()->find($order->id);
        return $fresh && $this->isAvailable() && $this->eligibilityError($fresh, false, true, false) === null;
    }

    public function recoveryUrl(Order $order): string
    {
        return URL::temporarySignedRoute('abandoned-cart.restore', now()->addDays((int) config('abandoned_cart.recovery_link_days', 7)), ['order' => $order->id]);
    }

    private function reserve(Order $order, int $sequence, string $source): AbandonedCartReminder
    {
        return DB::transaction(function () use ($order, $sequence, $source) {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($this->email($fresh) !== $this->email($order) || (int) $fresh->user_id !== (int) $order->user_id) {
                throw new RuntimeException('Podaci kupca promijenjeni su prije slanja podsjetnika.');
            }
            if ($error = $this->purchaseError($fresh)) {
                throw new RuntimeException($error);
            }
            $email = $this->email($fresh);
            $inserted = DB::table('abandoned_cart_reminders')->insertOrIgnore([
                'order_id' => $fresh->id,
                'sequence' => $sequence,
                'source' => $source,
                'recipient_email' => $email,
                'recipient_key' => hash('sha256', $email),
                'scheduled_for' => $this->scheduledFor($fresh, $sequence),
                'attempted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (! $inserted) {
                throw new RuntimeException('Podsjetnik je već poslan ili je slanje već pokušano.');
            }
            return AbandonedCartReminder::query()->where('order_id', $fresh->id)->where('sequence', $sequence)->firstOrFail();
        });
    }

    private function eligibilityError(Order $order, bool $automatic, bool $requireProducts = true, bool $checkMessages = true): ?string
    {
        if ($error = $this->purchaseError($order)) {
            return $error;
        }
        if (! filter_var($this->email($order), FILTER_VALIDATE_EMAIL)) {
            return 'Narudžba nema valjanu e-mail adresu kupca.';
        }
        if ($automatic && (! $this->startsAt() || Carbon::parse($order->created_at)->lt($this->startsAt())
            || Carbon::parse($order->unfinished_at ?: $order->created_at)->lt(now()->subDays((int) config('abandoned_cart.max_age_days', 3))))) {
            return 'Automatski podsjetnici ne šalju se za stare narudžbe.';
        }
        $latest = $this->identityQuery($order)->where('order_status_id', (int) config('settings.order.status.unfinished', 8))
            ->where('id', '!=', $order->id)
            ->where(function (Builder $q) use ($order) {
                $activity = $order->unfinished_at ?: $order->created_at;
                $q->whereRaw('COALESCE(unfinished_at, created_at) > ?', [$activity])
                    ->orWhere(function (Builder $same) use ($activity, $order) {
                        $same->whereRaw('COALESCE(unfinished_at, created_at) = ?', [$activity])->where('id', '>', $order->id);
                    });
            })->exists();
        if ($latest) {
            return 'Kupac već ima noviju nedovršenu košaricu.';
        }
        if ($requireProducts && ! $order->products()->exists()) {
            return 'Narudžba nema artikala za podsjetnik.';
        }
        if ($checkMessages && $this->hasOtherMessage($order)) {
            return 'Kupcu je podsjetnik ili promo poruka već poslana ili je slanje već pokušano.';
        }
        return null;
    }

    private function purchaseError(Order $order): ?string
    {
        if ((int) $order->order_status_id !== (int) config('settings.order.status.unfinished', 8)
            || $order->checkout_processed_at || $order->transactions()->where('success', 1)->exists()) {
            return 'Podsjetnik se može poslati samo za nedovršenu narudžbu.';
        }
        $statuses = $this->purchaseStatuses();
        $purchased = $this->identityQuery($order)->where('id', '!=', $order->id)
            ->where(function (Builder $q) use ($statuses) {
                $q->whereIn('order_status_id', $statuses)->orWhereNotNull('checkout_processed_at')
                    ->orWhereHas('transactions', fn (Builder $t) => $t->where('success', 1));
            })
            ->where(function (Builder $q) use ($order, $statuses) {
                $q->where('created_at', '>=', $order->created_at)
                    ->orWhere('checkout_processed_at', '>=', $order->created_at)
                    ->orWhereHas('history', fn (Builder $h) => $h->whereIn('status', $statuses)->where('created_at', '>=', $order->created_at))
                    ->orWhereHas('transactions', fn (Builder $t) => $t->where('success', 1)->where('created_at', '>=', $order->created_at));
            })->exists();

        return $purchased ? 'Kupac je u međuvremenu dovršio kupnju.' : null;
    }

    private function hasOtherMessage(Order $order): bool
    {
        $ownAttempt = AbandonedCartReminder::query()->where('order_id', $order->id)->where('sequence', 1)->exists();
        $history = OrderHistory::query()->where('order_id', $order->id)->where(function (Builder $q) use ($ownAttempt) {
            $q->where('comment', 'like', 'Poslan promo email za nedovrsenu narudzbu.%');
            if (! $ownAttempt) {
                $q->orWhere('comment', UnfinishedOrderPromoService::REMINDER_HISTORY_COMMENT);
            }
        })->exists();
        if ($history || app(UnfinishedOrderPromoService::class)->hasSentPromoForOrder($order)) {
            return true;
        }
        // A newly recreated checkout must not restart the same recipient's series.
        $otherAttempts = AbandonedCartReminder::query()->where('order_id', '!=', $order->id)
            ->where('attempted_at', '>=', now()->subHours((int) config('abandoned_cart.recipient_cooldown_hours', 72)))
            ->where(function (Builder $q) use ($order) {
                $q->where('recipient_key', hash('sha256', $this->email($order)));
                if ((int) $order->user_id > 0) {
                    $q->orWhereHas('order', fn (Builder $o) => $o->where('user_id', $order->user_id));
                }
            })->exists();
        if ($otherAttempts) {
            return true;
        }
        // Include old manual sends on sibling attempts, before the new ledger existed.
        return OrderHistory::query()->whereIn('order_id', $this->identityQuery($order)->select('id'))
            ->where('order_id', '!=', $order->id)
            ->where('created_at', '>=', now()->subHours((int) config('abandoned_cart.recipient_cooldown_hours', 72)))
            ->where(function (Builder $q) {
                $q->where('comment', UnfinishedOrderPromoService::REMINDER_HISTORY_COMMENT)
                    ->orWhere('comment', 'like', 'Poslan promo email za nedovrsenu narudzbu.%');
            })->exists();
    }

    private function identityQuery(Order $order): Builder
    {
        return Order::query()->where(function (Builder $q) use ($order) {
            $q->whereRaw('LOWER(TRIM(payment_email)) = ?', [$this->email($order)]);
            if ((int) $order->user_id > 0) {
                $q->orWhere('user_id', (int) $order->user_id);
            }
        });
    }

    private function withRecipientLock(Order $order, callable $callback)
    {
        $identity = Order::query()->findOrFail($order->id);
        $keys = ['abandoned-cart:email:' . hash('sha256', $this->email($identity))];
        if ((int) $identity->user_id > 0) {
            $keys[] = 'abandoned-cart:user:' . (int) $identity->user_id;
        }
        sort($keys);
        $locks = [];
        try {
            foreach ($keys as $key) {
                $lock = Cache::lock($key, 120);
                if (! $lock->get()) {
                    throw new RuntimeException('Slanje podsjetnika za ovog kupca već je u tijeku.');
                }
                $locks[] = $lock;
            }
            $current = Order::query()->findOrFail($order->id);
            if ($this->email($current) !== $this->email($identity) || (int) $current->user_id !== (int) $identity->user_id) {
                throw new RuntimeException('Podaci kupca promijenjeni su prije slanja podsjetnika.');
            }
            return $callback($current);
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    private function startsAt(): ?Carbon
    {
        $value = config('abandoned_cart.starts_at');
        if (! $value && Schema::hasTable('settings')) {
            $value = DB::table('settings')->where('code', 'order')->where('key', 'abandoned_cart_reminders_started_at')->whereNull('user_id')->value('value');
        }
        return $value ? Carbon::parse($value, config('app.timezone', 'Europe/Zagreb')) : null;
    }

    private function delay(int $sequence): int
    {
        if (! in_array($sequence, [1, 2], true)) {
            throw new RuntimeException('Nepoznat redni broj podsjetnika.');
        }
        return max(1, (int) config('abandoned_cart.delays_minutes.' . $sequence, $sequence === 1 ? 60 : 1440));
    }

    private function minimumGap(): int
    {
        return max(1, (int) config('abandoned_cart.min_gap_minutes', 1380));
    }

    private function purchaseStatuses(): array
    {
        return array_values(array_unique(array_map('intval', [1, 2, 3, 4, 6, 9, 10, 11,
            config('settings.order.status.new', 1), config('settings.order.status.awaiting_payment', 2),
            config('settings.order.status.paid', 3), config('settings.order.status.send', 4),
            config('settings.order.status.completed', 9), config('settings.order.status.ready', 10),
            config('settings.order.status.processing', 11)])));
    }

    private function email(Order $order): string
    {
        return mb_strtolower(trim((string) $order->payment_email));
    }

    private function recordError(AbandonedCartReminder $claim, string $message): void
    {
        try {
            $claim->forceFill(['last_error' => mb_substr($message, 0, 2000)])->save();
        } catch (\Throwable $exception) {
            $this->logWarning('Could not save reminder failure; reservation retained.', $claim->order_id, $exception);
        }
    }

    private function logWarning(string $message, int $orderId, \Throwable $exception): void
    {
        try {
            Log::warning($message, ['order_id' => $orderId, 'error' => $exception->getMessage()]);
        } catch (\Throwable $ignored) {
            // Delivery evidence must survive even a failing logger.
        }
    }
}
