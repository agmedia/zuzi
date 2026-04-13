<?php

namespace App\Models\Back\Settings;

use App\Helpers\Helper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Settings extends Model
{

    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'settings';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var Request
     */
    protected $request;

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @param string $code
     * @param string $key
     *
     * @return false|Collection
     */
    public static function get(string $code, string $key)
    {
        if (! Schema::hasTable('settings')) {
            return static::fallbackValue($code, $key);
        }

        $styles = Helper::resolveCache('settings')->remember($code.$key, config('cache.life'), function () use ($code, $key) {
            return Settings::where('code', $code)->where('key', $key)->first();
        });

        if ($styles) {
            if ($styles->json) {
                return collect(json_decode($styles->value));
            }

            return $styles->value;
        }

        return static::fallbackValue($code, $key);
    }


    /**
     * @param string $code
     * @param string $key
     *
     * @return false|Collection
     */
    public static function getList(string $code, string $key = 'list.%', bool $only_active = true)
    {
        if (! Schema::hasTable('settings')) {
            return static::fallbackList($code, $key, $only_active);
        }

        $styles = Helper::resolveCache('settings')->remember($code, config('cache.life'), function () use ($code, $key) {
            return Settings::where('code', $code)->where('key', 'like', $key)->get();
        });

        if ($styles->count()) {
            $return_styles = collect();

            foreach ($styles as $style) {
                if ($style->json) {
                    $temp_style = collect(json_decode($style->value))->all();

                    foreach ($temp_style as $item) {
                        $return_styles->put($item->title, $item);
                    }
                }
            }

            if ($only_active) {
                return $return_styles->where('status')->sortBy('sort_order');
            }

            return $return_styles->sortBy('sort_order');
        }

        return static::fallbackList($code, $key, $only_active);
    }

    /**
     * Minimal front-end payload when the settings table is unavailable.
     */
    public static function frontApiDefaults(): array
    {
        return [
            'currency.list' => static::fallbackCurrencyList()->values()->map(fn ($item) => json_decode(json_encode($item), true))->all(),
            'geo_zone.list' => static::fallbackGeoZones()->values()->map(fn ($item) => json_decode(json_encode($item), true))->all(),
            'payment.list' => static::fallbackPaymentMethods(false)->values()->map(fn ($item) => json_decode(json_encode($item), true))->all(),
            'shipping.list' => static::fallbackShippingMethods(false)->values()->map(fn ($item) => json_decode(json_encode($item), true))->all(),
            'tax.list' => static::fallbackTaxes()->values()->map(fn ($item) => json_decode(json_encode($item), true))->all(),
        ];
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @param string $code
     * @param string $key
     * @param        $value
     * @param bool   $json
     *
     * @return bool|mixed
     */
    public static function set(string $code, string $key, $value, bool $json = true)
    {
        $setting = Settings::where('code', $code)->where('key', $key)->first();

        if ($setting) {
            if ($json) {
                $values = collect(json_decode($setting->value));

                if ( ! $values->contains($value)) {
                    $values->push($value);
                }

                $value = json_encode($values);
            }

            return self::edit($setting->id, $code, $key, $value, $json);
        }

        if ($json) {
            $values = [$value];

            $value = json_encode($values);
        }

        return self::insert($code, $key, $value, $json);
    }


    /**
     * @param string $code
     * @param string $key
     * @param        $value
     * @param bool   $json
     *
     * @return bool|mixed
     */
    public static function setListItem(string $code, string $key, $value)
    {
        $updated = false;
        $setting = Settings::where('code', $code)->where('key', $key)->first();

        if ($setting) {
            $updated = $setting->update([
                'value' => json_encode([$value])
            ]);
        }

        return $updated ?: false;
    }


    /**
     * @param string $key
     * @param mixed  $value
     * @param bool   $json
     *
     * @return mixed
     */
    public static function setProduct(string $key, $value, bool $json = true)
    {
        $styles = Settings::where('code', 'product')->where('key', $key)->first();

        if ($styles) {
            if ($json) {
                $values = collect(json_decode($styles->value));

                if ( ! $values->contains($value)) {
                    $values->push($value);
                }

                $value = json_encode($values);
            }

            return self::edit($styles->id, 'product', $key, $value, $json);
        }

        if ($json) {
            $values = [$value];

            $value = json_encode($values);
        }

        return self::insert('product', $key, $value, $json);
    }


    /**
     * @param string $code
     * @param string $key
     * @param        $value
     * @param bool   $json
     *
     * @return mixed
     */
    public static function insert(string $code, string $key, $value, bool $json)
    {
        return self::insertGetId([
            'code'       => $code,
            'key'        => $key,
            'value'      => $value,
            'json'       => $json,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }


    /**
     * @param int    $id
     * @param string $code
     * @param string $key
     * @param        $value
     * @param bool   $json
     *
     * @return bool
     */
    public static function edit(int $id, string $code, string $key, $value, bool $json)
    {
        return self::where('id', $id)->update([
            'code'       => $code,
            'key'        => $key,
            'value'      => $value,
            'json'       => $json,
            'updated_at' => Carbon::now()
        ]);
    }


    /**
     * @param string $code
     * @param string $key
     *
     * @return mixed
     */
    public static function erase(string $code, string $key)
    {
        return self::where('code', $code)->where('key', $key)->delete();
    }

    private static function fallbackValue(string $code, string $key)
    {
        if ($key === 'list') {
            return static::fallbackList($code);
        }

        if (str_starts_with($key, 'list.')) {
            $lookup = substr($key, 5);
            $item = static::fallbackList($code, 'list.%', false)->first(function ($candidate) use ($lookup) {
                return (string) ($candidate->code ?? '') === $lookup
                    || (string) ($candidate->id ?? '') === $lookup
                    || (string) ($candidate->title ?? '') === $lookup;
            });

            return $item ? collect([$item]) : collect();
        }

        return match ($code . '.' . $key) {
            'action.group_list' => static::fallbackActionGroups(),
            'action.type_list' => static::fallbackActionTypes(),
            'order.statuses' => static::fallbackOrderStatuses(),
            default => collect(),
        };
    }

    private static function fallbackList(string $code, string $key = 'list.%', bool $only_active = true): Collection
    {
        $items = match ($code) {
            'currency' => static::fallbackCurrencyList(),
            'geo_zone' => static::fallbackGeoZones(),
            'payment' => static::fallbackPaymentMethods($only_active),
            'shipping' => static::fallbackShippingMethods($only_active),
            'tax' => static::fallbackTaxes(),
            default => collect(),
        };

        if (str_starts_with($key, 'list.') && $key !== 'list.%' && $key !== 'list') {
            $lookup = substr($key, 5);

            $items = $items->filter(function ($item) use ($lookup) {
                return (string) ($item->code ?? '') === $lookup
                    || (string) ($item->id ?? '') === $lookup
                    || (string) ($item->title ?? '') === $lookup;
            })->values();
        }

        if ($only_active) {
            $items = $items->filter(fn ($item) => (bool) ($item->status ?? true))->values();
        }

        return $items;
    }

    private static function fallbackCurrencyList(): Collection
    {
        $eurToHrk = (float) config('settings.eur_divide_amount', 0.13272280);
        $hrkValue = $eurToHrk > 0 ? round(1 / $eurToHrk, 4) : 7.5345;

        return collect([
            (object) [
                'id' => 1,
                'title' => 'Euro',
                'code' => 'EUR',
                'value' => 1.0,
                'symbol_left' => '',
                'symbol_right' => ' €',
                'decimal_places' => 2,
                'main' => true,
                'status' => true,
                'sort_order' => 1,
            ],
            (object) [
                'id' => 2,
                'title' => 'Hrvatska kuna',
                'code' => 'HRK',
                'value' => $hrkValue,
                'symbol_left' => '',
                'symbol_right' => ' kn',
                'decimal_places' => 2,
                'main' => false,
                'status' => true,
                'sort_order' => 2,
            ],
        ]);
    }

    private static function fallbackGeoZones(): Collection
    {
        return collect([
            (object) [
                'id' => 1,
                'title' => 'Sve zone',
                'state' => [],
                'status' => true,
                'sort_order' => 1,
            ],
        ]);
    }

    private static function fallbackPaymentMethods(bool $only_active = true): Collection
    {
        $titles = [
            'corvus' => 'Kartično plaćanje',
            'cod' => 'Plaćanje pouzećem',
            'bank' => 'Bankovna uplata',
            'pickup' => 'Plaćanje pri preuzimanju',
        ];

        $items = collect(config('settings.payment.providers', []))
            ->keys()
            ->values()
            ->map(function ($code, $index) use ($titles) {
                return (object) [
                    'id' => $index + 1,
                    'title' => $titles[$code] ?? strtoupper($code),
                    'code' => $code,
                    'geo_zone' => 1,
                    'status' => true,
                    'sort_order' => $index + 1,
                    'data' => (object) [
                        'price' => 0,
                        'short_description' => '',
                        'description' => '',
                    ],
                ];
            });

        return $only_active
            ? $items->filter(fn ($item) => (bool) $item->status)->values()
            : $items->values();
    }

    private static function fallbackShippingMethods(bool $only_active = true): Collection
    {
        return collect();
    }

    private static function fallbackTaxes(): Collection
    {
        return collect([
            (object) [
                'id' => 1,
                'title' => 'PDV 25%',
                'rate' => 25,
                'status' => true,
                'sort_order' => 1,
            ],
        ]);
    }

    private static function fallbackActionGroups(): Collection
    {
        return collect([
            (object) ['id' => 'product', 'title' => 'Proizvod'],
            (object) ['id' => 'category', 'title' => 'Kategorija'],
            (object) ['id' => 'author', 'title' => 'Autor'],
            (object) ['id' => 'publisher', 'title' => 'Nakladnik'],
            (object) ['id' => 'all', 'title' => 'Svi proizvodi'],
            (object) ['id' => 'total', 'title' => 'Ukupna košarica'],
        ]);
    }

    private static function fallbackActionTypes(): Collection
    {
        return collect([
            (object) ['id' => 'P', 'title' => 'Postotak'],
            (object) ['id' => 'F', 'title' => 'Fiksni iznos'],
        ]);
    }

    private static function fallbackOrderStatuses(): Collection
    {
        return collect([
            (object) ['id' => (int) config('settings.order.status.new'), 'title' => 'Nova', 'sort_order' => 1],
            (object) ['id' => (int) config('settings.order.status.unfinished'), 'title' => 'Nedovršena', 'sort_order' => 2],
            (object) ['id' => (int) config('settings.order.status.paid'), 'title' => 'Plaćena', 'sort_order' => 3],
            (object) ['id' => (int) config('settings.order.status.send'), 'title' => 'Poslana', 'sort_order' => 4],
            (object) ['id' => (int) config('settings.order.status.canceled'), 'title' => 'Otkazana', 'sort_order' => 5],
            (object) ['id' => (int) config('settings.order.status.declined'), 'title' => 'Odbijena', 'sort_order' => 6],
            (object) ['id' => (int) config('settings.order.status.ready'), 'title' => 'Spremna', 'sort_order' => 7],
        ]);
    }
}
