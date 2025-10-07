<?php

namespace App\Services\WoltDrive;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class WoltZoneService
{
    private string $kmlPath;

    public function __construct(?string $kmlPath = null)
    {
        // Pametno razrješavanje putanje iz .env
        $envPath = $kmlPath ?: env('WOLT_KML_PATH', 'storage/app/wolt/zone.kml');

        // Ako počinje s "public/", koristi public_path
        if (str_starts_with($envPath, 'public/')) {
            $this->kmlPath = public_path(substr($envPath, strlen('public/')));
        } elseif (str_starts_with($envPath, 'storage/')) {
            $this->kmlPath = base_path($envPath);
        } elseif (File::isFile($envPath)) {
            $this->kmlPath = $envPath; // već apsolutna
        } else {
            $this->kmlPath = base_path($envPath);
        }

        if (!File::exists($this->kmlPath)) {
            Log::warning('[WOLT] KML file not found', ['path' => $this->kmlPath, 'env' => $envPath]);
        }
    }

    /** Public API */
    public function containsLatLng(float $lat, float $lng): bool
    {
        $polygons = $this->polygons();
        if (empty($polygons)) {
            Log::warning('[WOLT] containsLatLng: No polygons loaded', ['path' => $this->kmlPath]);
            return false;
        }

        foreach ($polygons as $poly) {
            // Podrška za rupe: ['outer'=>[...], 'inners'=>[[...],[...]]]
            $outer  = $poly['outer'];
            $inners = $poly['inners'] ?? [];

            $inOuter = $this->pointInPolygon($lat, $lng, $outer);
            if (!$inOuter) {
                continue;
            }
            foreach ($inners as $hole) {
                if ($this->pointInPolygon($lat, $lng, $hole)) {
                    // Upao u rupu → tretiraj kao izvan
                    $inOuter = false;
                    break;
                }
            }
            if ($inOuter) {
                return true;
            }
        }
        return false;
    }

    public function containsAddress(string $address): bool
    {
        $coords = $this->geocode($address);
        if (!$coords) {
            Log::warning('[WOLT] Geocode failed', ['address' => $address]);
            return false;
        }
        Log::debug('[WOLT] Geocode OK', $coords);
        return $this->containsLatLng($coords['lat'], $coords['lng']);
    }

    /** ------------ internals ------------- */

    protected function polygons(): array
    {
        $cacheKey = env('WOLT_ZONE_CACHE_KEY', 'wolt_zone_polygons');

        $sig = '0';
        if (File::exists($this->kmlPath)) {
            $sig = File::lastModified($this->kmlPath) . ':' . File::size($this->kmlPath);
        }

        return Cache::remember($cacheKey . ':' . $sig, 86400, function () {
            $polys = $this->parseKmlToPolygons($this->kmlPath);
            Log::debug('[WOLT] KML loaded', [
                'path' => $this->kmlPath,
                'polygons_count' => count($polys),
            ]);
            if (!empty($polys)) {
                // logiraj prvih par točaka prvog poligona
                $preview = array_slice($polys[0]['outer'], 0, 3);
                Log::debug('[WOLT] First polygon preview (lat,lng)', $preview);
            }
            return $polys;
        });
    }

    /**
     * Vraća listu poligona:
     * [
     *   ['outer' => [[lat,lng]...], 'inners' => [ [[lat,lng]...], ... ]],
     *   ...
     * ]
     */
    /**
     * Pokuša detektirati KML namespace (bilo koji prefiks) i registrira ga pod "kml".
     * Vrati true ako je našao namespace, false ako dok. nema namespacea (onda koristimo XPaths bez prefiksa).
     */
    protected function detectAndRegisterKmlNs(SimpleXMLElement $xml): bool
    {
        // Pokupi sve NS-ove, i deklarirane na čvorovima
        $namespaces = $xml->getDocNamespaces(true) ?: [];

        // Traži onaj koji sadrži 'opengis.net/kml' (npr. http://www.opengis.net/kml/2.2)
        foreach ($namespaces as $prefix => $uri) {
            if (stripos($uri, 'opengis.net/kml') !== false) {
                $xml->registerXPathNamespace('kml', $uri);
                return true;
            }
        }

        // Ponekad KML ima default NS bez prefiksa – SimpleXML ga ne treba za ne-prefiksirane XPathove
        return false;
    }

    /**
     * Vraća listu poligona:
     * [
     *   ['outer' => [[lat,lng]...], 'inners' => [ [[lat,lng]...], ... ]],
     *   ...
     * ]
     */
    protected function parseKmlToPolygons(string $path): array
    {
        if (!\Illuminate\Support\Facades\File::exists($path)) {
            return [];
        }

        // Spriječi PHP warnings iz SimpleXML-a
        $prev = libxml_use_internal_errors(true);
        $xml  = new \SimpleXMLElement(\Illuminate\Support\Facades\File::get($path));
        libxml_use_internal_errors($prev);

        $polygons = [];

        // 1) Nađi SVE <Polygon> bez obzira na namespace/prefiks
        $polygonNodes = $xml->xpath("//*[local-name()='Polygon']") ?: [];

        foreach ($polygonNodes as $polygonNode) {
            // 2) Uhvati outerBoundaryIs → LinearRing → coordinates (namespace-agnostično)
            $outerNodes = $polygonNode->xpath(".//*[local-name()='outerBoundaryIs']/*[local-name()='LinearRing']/*[local-name()='coordinates']") ?: [];

            // 3) InnerBoundaryIs (rupe)
            $innerNodes = $polygonNode->xpath(".//*[local-name()='innerBoundaryIs']/*[local-name()='LinearRing']/*[local-name()='coordinates']") ?: [];

            if (!$outerNodes || !isset($outerNodes[0])) {
                continue;
            }

            $outer  = $this->coordsTextToArray(trim((string) $outerNodes[0]));
            $inners = [];

            foreach ($innerNodes as $inner) {
                $inners[] = $this->coordsTextToArray(trim((string) $inner));
            }

            if (!empty($outer)) {
                $polygons[] = [
                    'outer'  => $outer,
                    'inners' => $inners,
                ];
            }
        }

        return $polygons;
    }



    /** Pretvorba KML coordinates -> [[lat,lng], ...]; KML je lon,lat[,alt] */
    protected function coordsTextToArray(string $coordsText): array
    {
        $pairs = preg_split('/\s+/', trim($coordsText));
        $poly  = [];

        foreach ($pairs as $pair) {
            if (str_contains($pair, ',')) {
                $parts = explode(',', $pair);
                // Neki KML-ovi imaju i altitude -> uzmi prva 2
                $lng = (float) ($parts[0] ?? 0);
                $lat = (float) ($parts[1] ?? 0);
                $poly[] = ['lat' => $lat, 'lng' => $lng];
            }
        }

        // ukloni zadnju točku ako duplira prvu
        if (count($poly) > 2) {
            $first = $poly[0];
            $last  = $poly[count($poly)-1];
            if (abs($first['lat'] - $last['lat']) < 1e-9 && abs($first['lng'] - $last['lng']) < 1e-9) {
                array_pop($poly);
            }
        }

        return $poly;
    }

    /** Ray-casting */
    protected function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $j = count($polygon) - 1;

        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i]['lng']; $yi = $polygon[$i]['lat'];
            $xj = $polygon[$j]['lng']; $yj = $polygon[$j]['lat'];

            $intersect = (($yi > $lat) !== ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);

            if ($intersect) $inside = !$inside;
            $j = $i;
        }
        return $inside;
    }

    /** Minimalni geokoder (Nominatim). Možeš zamijeniti Google-om. */
    protected function geocode(string $address): ?array
    {
        try {
            $resp = \Http::timeout(7)->withHeaders([
                'User-Agent' => 'WoltZoneChecker/1.0 (+contact@example.com)'
            ])->get('https://nominatim.openstreetmap.org/search', [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 0,
            ]);

            if ($resp->successful()) {
                $json = $resp->json();
                if (!empty($json[0]['lat']) && !empty($json[0]['lon'])) {
                    return [
                        'lat' => (float) $json[0]['lat'],
                        'lng' => (float) $json[0]['lon'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[WOLT] Geocode exception', ['msg' => $e->getMessage()]);
        }
        return null;
    }
}
