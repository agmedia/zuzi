<?php

namespace App\Console\Commands;

use App\Services\WoltDrive\WoltZoneService;
use Illuminate\Console\Command;

class WoltTestZone extends Command
{
    protected $signature = 'wolt:test-zone {--address=} {--lat=} {--lng=}';
    protected $description = 'Provjeri je li adresa ili lat/lng unutar Wolt KML zone';

    public function handle(WoltZoneService $zone)
    {
        $lat = $this->option('lat');
        $lng = $this->option('lng');
        $address = $this->option('address');

        if ($lat !== null && $lng !== null) {
            $in = $zone->containsLatLng((float)$lat, (float)$lng);
            $this->info("LatLng [$lat,$lng] => " . ($in ? 'IN' : 'OUT'));
            return self::SUCCESS;
        }

        if ($address) {
            $in = $zone->containsAddress($address);
            $this->info("Address \"$address\" => " . ($in ? 'IN' : 'OUT'));
            return self::SUCCESS;
        }

        $this->error('Provide either --lat= and --lng= OR --address="..."');
        return self::INVALID;
    }
}
