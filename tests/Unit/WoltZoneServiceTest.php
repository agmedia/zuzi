<?php

namespace Tests\Unit;

use App\Services\WoltDrive\WoltZoneService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WoltZoneServiceTest extends TestCase
{
    public function test_contains_address_uses_app_contact_user_agent_for_geocoding(): void
    {
        config([
            'app.name' => 'Zuzi',
            'app.url' => 'https://zuzi.hr',
            'mail.from.address' => 'info@zuzi.hr',
        ]);

        Http::fake([
            'https://nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '45.8596707',
                    'lon' => '15.9737143',
                ],
            ], 200),
        ]);

        $zone = new WoltZoneService($this->writeKmlZone());

        $this->assertTrue($zone->containsAddress('Lonjšćina 1, Zagreb, 10000, Croatia'));

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('User-Agent', 'Zuzi WoltZoneChecker/1.0 (https://zuzi.hr; info@zuzi.hr)')
                && $request['email'] === 'info@zuzi.hr'
                && $request['q'] === 'Lonjšćina 1, Zagreb, 10000, Croatia';
        });
    }

    private function writeKmlZone(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wolt-zone-');

        file_put_contents($path, <<<'KML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <Polygon>
        <outerBoundaryIs>
          <LinearRing>
            <coordinates>
              15.970,45.857 15.977,45.857 15.977,45.862 15.970,45.862 15.970,45.857
            </coordinates>
          </LinearRing>
        </outerBoundaryIs>
      </Polygon>
    </Placemark>
  </Document>
</kml>
KML);

        $this->beforeApplicationDestroyed(function () use ($path) {
            @unlink($path);
        });

        return $path;
    }
}
