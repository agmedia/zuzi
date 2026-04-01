<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AddressDirectoryService
{
    public function findByPostal(string $postalCode, string $country = 'Croatia'): ?array
    {
        if ( ! $this->isCroatia($country)) {
            return null;
        }

        $postalCode = $this->normalizePostal($postalCode);

        if ($postalCode === '') {
            return null;
        }

        foreach ($this->places() as $place) {
            if ($this->normalizePostal($place['postal_code'] ?? '') === $postalCode) {
                return $this->normalizePlace($place);
            }
        }

        return null;
    }

    public function findByCity(string $city, string $country = 'Croatia'): ?array
    {
        if ( ! $this->isCroatia($country)) {
            return null;
        }

        $city = $this->normalizeText($city);

        if ($city === '') {
            return null;
        }

        foreach ($this->places() as $place) {
            if ($this->normalizeText($place['city'] ?? '') === $city) {
                return $this->normalizePlace($place);
            }
        }

        return null;
    }

    private function places(): array
    {
        static $places = null;

        if (is_array($places)) {
            return $places;
        }

        $payload = '';

        if (Storage::disk('assets')->exists('hr-places.json')) {
            $payload = (string) Storage::disk('assets')->get('hr-places.json');
        } else {
            $fallbackPath = dirname(base_path()) . '/shop/public/front-theme/data/hr-places.json';

            if (is_file($fallbackPath)) {
                $payload = (string) file_get_contents($fallbackPath);
            }
        }

        $decoded = json_decode($payload, true);
        $places = array_values(array_filter((array) ($decoded['places'] ?? []), 'is_array'));

        return $places;
    }

    private function normalizePlace(array $place): array
    {
        return [
            'postal_code' => trim((string) ($place['postal_code'] ?? '')),
            'city' => trim((string) ($place['city'] ?? '')),
            'county' => trim((string) ($place['county'] ?? '')),
            'country_code' => strtoupper(trim((string) ($place['country_code'] ?? 'HR'))),
        ];
    }

    private function isCroatia(string $country): bool
    {
        $country = trim($country);

        return strtoupper($country) === 'HR' || $this->normalizeText($country) === 'croatia';
    }

    private function normalizePostal(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function normalizeText(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }
}
