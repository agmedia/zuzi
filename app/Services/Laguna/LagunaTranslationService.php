<?php

namespace App\Services\Laguna;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LagunaTranslationService
{
    private const ENDPOINT = 'https://translate.googleapis.com/translate_a/single';
    private const MAX_INPUT_BYTES = 100000;

    public function translateDescription(string $description): string
    {
        $description = trim($description);
        if ($description === '') {
            return '';
        }

        if (strlen($description) > self::MAX_INPUT_BYTES) {
            throw new RuntimeException('Opis je predugačak za automatski prijevod.');
        }

        $response = Http::asForm()
            ->withOptions(['connect_timeout' => 5])
            ->timeout(20)
            ->withHeaders(['User-Agent' => 'Zuzi-Laguna-Importer/1.0'])
            ->post(self::ENDPOINT . '?client=gtx&sl=sr&tl=hr&dt=t', [
                'q' => $description,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Prijevod opisa nije uspio (HTTP ' . $response->status() . ').');
        }

        $decoded = $response->json();
        if (! is_array($decoded) || empty($decoded[0]) || ! is_array($decoded[0])) {
            throw new RuntimeException('Servis za prijevod vratio je neispravan odgovor.');
        }

        $translated = '';
        foreach ($decoded[0] as $segment) {
            if (is_array($segment) && isset($segment[0]) && is_string($segment[0])) {
                $translated .= $segment[0];
            }
        }

        $translated = trim($translated);
        if ($translated === '') {
            throw new RuntimeException('Servis za prijevod vratio je prazan opis.');
        }

        return $translated;
    }
}
