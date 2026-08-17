<?php

namespace App\Services;

use App\Models\Back\Catalog\Product\Product;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductIdentifierAllocator
{
    public const SESSION_KEY = 'product_identifier_reservation_token';

    private const LOCK_TABLE = 'product_identifier_allocation_locks';
    private const RESERVATION_TABLE = 'product_identifier_reservations';
    private const RESERVATION_MINUTES = 120;
    private const SEARCH_BATCH_SIZE = 500;

    /**
     * Reserve the first available SKU and Pelion ItemID for the create form.
     *
     * @return array{token: string, sku: int, itemid: int}
     */
    public function reserve(?string $existingToken = null): array
    {
        return DB::transaction(function () use ($existingToken) {
            $this->acquireLock();
            $this->deleteExpiredReservations();

            $reservation = $this->findUsableReservation($existingToken);

            if ($reservation) {
                DB::table(self::RESERVATION_TABLE)
                    ->where('id', $reservation->id)
                    ->update([
                        'expires_at' => now()->addMinutes(self::RESERVATION_MINUTES),
                        'updated_at' => now(),
                    ]);

                return $this->reservationData($reservation);
            }

            if ($existingToken) {
                DB::table(self::RESERVATION_TABLE)->where('token', $existingToken)->delete();
            }

            $identifiers = $this->nextAvailableIdentifiers();
            $token = (string) Str::uuid();

            DB::table(self::RESERVATION_TABLE)->insert([
                'token' => $token,
                'sku' => $identifiers['sku'],
                'itemid' => $identifiers['itemid'],
                'expires_at' => now()->addMinutes(self::RESERVATION_MINUTES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'token' => $token,
                'sku' => $identifiers['sku'],
                'itemid' => $identifiers['itemid'],
            ];
        }, 3);
    }

    /**
     * Confirm a reservation while the product is inserted. If the reservation
     * expired or its number was taken by another process, fresh numbers are used.
     *
     * @template T
     * @param Closure(array{sku: int, itemid: int}): T $callback
     * @return T
     */
    public function confirm(?string $token, Closure $callback)
    {
        return DB::transaction(function () use ($token, $callback) {
            $this->acquireLock();
            $this->deleteExpiredReservations();

            $reservation = $this->findUsableReservation($token);

            if ($reservation) {
                $identifiers = [
                    'sku' => (int) $reservation->sku,
                    'itemid' => (int) $reservation->itemid,
                ];
            } else {
                if ($token) {
                    DB::table(self::RESERVATION_TABLE)->where('token', $token)->delete();
                }

                $identifiers = $this->nextAvailableIdentifiers();
            }

            $result = $callback($identifiers);

            if ($result && $token) {
                DB::table(self::RESERVATION_TABLE)->where('token', $token)->delete();
            }

            return $result;
        }, 3);
    }

    /**
     * @return array{sku: int, itemid: int}
     */
    private function nextAvailableIdentifiers(): array
    {
        return [
            'sku' => $this->nextAvailableNumber('sku'),
            'itemid' => $this->nextAvailableNumber('itemid'),
        ];
    }

    private function nextAvailableNumber(string $column): int
    {
        if (! in_array($column, ['sku', 'itemid'], true)) {
            throw new RuntimeException('Unsupported product identifier column.');
        }

        $candidate = 1;

        while ($candidate < PHP_INT_MAX - self::SEARCH_BATCH_SIZE) {
            $lastCandidate = $candidate + self::SEARCH_BATCH_SIZE - 1;
            $candidates = range($candidate, $lastCandidate);
            $queryValues = $column === 'sku'
                ? array_map('strval', $candidates)
                : $candidates;

            $used = [];

            Product::query()
                ->whereIn($column, $queryValues)
                ->pluck($column)
                ->each(function ($value) use (&$used) {
                    $used[(int) $value] = true;
                });

            DB::table(self::RESERVATION_TABLE)
                ->where('expires_at', '>', now())
                ->whereIn($column, $candidates)
                ->pluck($column)
                ->each(function ($value) use (&$used) {
                    $used[(int) $value] = true;
                });

            foreach ($candidates as $number) {
                if (! isset($used[$number])) {
                    return $number;
                }
            }

            $candidate = $lastCandidate + 1;
        }

        throw new RuntimeException('No available product identifier could be found.');
    }

    private function acquireLock(): void
    {
        $lock = DB::table(self::LOCK_TABLE)
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if (! $lock) {
            throw new RuntimeException('Product identifier allocation lock is missing.');
        }
    }

    private function deleteExpiredReservations(): void
    {
        DB::table(self::RESERVATION_TABLE)
            ->where('expires_at', '<=', now())
            ->delete();
    }

    private function findUsableReservation(?string $token): ?object
    {
        if (! $token) {
            return null;
        }

        $reservation = DB::table(self::RESERVATION_TABLE)
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $reservation) {
            return null;
        }

        $isTaken = Product::query()
            ->where('sku', (string) $reservation->sku)
            ->orWhere('itemid', (int) $reservation->itemid)
            ->exists();

        return $isTaken ? null : $reservation;
    }

    /**
     * @return array{token: string, sku: int, itemid: int}
     */
    private function reservationData(object $reservation): array
    {
        return [
            'token' => (string) $reservation->token,
            'sku' => (int) $reservation->sku,
            'itemid' => (int) $reservation->itemid,
        ];
    }
}
