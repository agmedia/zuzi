<?php

namespace App\Models\Front\Checkout;

use App\Models\Back\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Class GeoZone
 * @package App\Models\Front\Checkout
 */
class GeoZone
{

    /**
     * @var array|false|Collection
     */
    protected $geo_zones;


    /**
     * GeoZone constructor.
     */
    public function __construct()
    {
        $this->geo_zones = $this->list();
    }


    /**
     * @param bool $only_active
     *
     * @return array|false|Collection
     */
    public function list(bool $only_active = true)
    {
        return Settings::getList('geo_zone', 'list', $only_active);
    }


    /**
     * @param int $id
     *
     * @return mixed
     */
    public function id(int $id)
    {
        return $this->geo_zones->where('id', $id)->first();
    }


    /**
     * @param string $title
     *
     * @return mixed
     */
    public function find(string $title)
    {
        return $this->geo_zones->where('title', $title)->first();
    }


    /**
     * @param string $state
     *
     * @return \stdClass
     */
    public function findState(string $state): \stdClass
    {
        foreach ($this->geo_zones as $geo_zone) {
            if (collect($geo_zone->state)->search($state)) {
                return $geo_zone;
            }
        }

        return $this->findApplicableToAll();
    }


    /**
     * @return \stdClass
     */
    public function findApplicableToAll(): \stdClass
    {
        foreach ($this->geo_zones as $geo_zone) {
            if (empty($geo_zone->state)) {
                return $geo_zone;
            }
        }

        return new \stdClass();
    }
}