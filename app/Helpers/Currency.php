<?php

namespace App\Helpers;

use App\Models\Back\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Currency
{

    /**
     * @return Collection
     */
    public static function list(): Collection
    {
        return static::loadCurrentCurrencyList();
    }


    /**
     * @param      $price
     * @param bool $text_price
     *
     * @return Collection|string|bool
     */
    public static function main($price = null, bool $text_price = false)
    {
        $currency = static::list()->first(function ($item) {
            return (bool) ($item->status ?? false) && (bool) ($item->main ?? false);
        });

        return static::resolveCurrency($currency, $price, $text_price);
    }


    /**
     * @param      $price
     * @param bool $text_price
     *
     * @return Collection|string|bool
     */
    public static function secondary($price = null, bool $text_price = false)
    {
        $currency = static::list()->first(function ($item) {
            return (bool) ($item->status ?? false) && ! (bool) ($item->main ?? false);
        });

        return static::resolveCurrency($currency, $price, $text_price);
    }


    /**
     * @return string
     */
    public static function main_symbol(): string
    {
        $currency = self::main();

        if ($currency) {
            return $currency->symbol_left ?: $currency->symbol_right;
        }

        return '€';
    }

    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    /**
     * @param      $currency
     * @param      $price
     * @param bool $text_price
     *
     * @return false|mixed|string
     */
    private static function resolveCurrency($currency, $price, bool $text_price = false)
    {
        if ($currency) {
            if ($price) {
                return static::resolvePrice($currency, $price, $text_price);
            }

            return $currency;
        }

        return false;
    }


    private static function loadCurrentCurrencyList(): Collection
    {
        try {
            if (! Schema::hasTable('settings')) {
                return static::normalizeCurrencyList(Settings::frontApiDefaults()['currency.list'] ?? []);
            }

            $setting = Settings::query()
                ->where('code', 'currency')
                ->where('key', 'list')
                ->first();

            if ($setting && $setting->json) {
                return static::normalizeCurrencyList(json_decode($setting->value) ?: []);
            }
        } catch (\Throwable $exception) {
            return static::normalizeCurrencyList(Settings::frontApiDefaults()['currency.list'] ?? []);
        }

        return static::normalizeCurrencyList(Settings::frontApiDefaults()['currency.list'] ?? []);
    }


    private static function normalizeCurrencyList(iterable $items): Collection
    {
        return collect($items)->map(function ($item) {
            if (is_array($item)) {
                return (object) $item;
            }

            return $item;
        })->values();
    }


    /**
     * @param stdClass   $currency
     * @param            $price
     * @param bool       $text_price
     *
     * @return string
     */
    private static function resolvePrice(\stdClass $currency, $price, bool $text_price = false): string
    {
        if ($text_price) {
            $left  = $currency->symbol_left ? $currency->symbol_left . '' : '';
            $right = $currency->symbol_right ? '' . $currency->symbol_right : '';

            return $left . number_format(($price * $currency->value), $currency->decimal_places, ',', '.') . $right;
        }

        return number_format(($price * $currency->value), $currency->decimal_places, '.', '');
    }
}
