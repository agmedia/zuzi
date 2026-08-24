<?php

namespace App\Services\Delfi;

use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use XMLReader;

class DelfiFeedService
{
    private DelfiFeedNormalizer $normalizer;

    public function __construct(DelfiFeedNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function feedUrl(): string
    {
        return (string) config('delfi_import.feed_url');
    }

    public function cachePath(): string
    {
        return (string) config('delfi_import.cache_path');
    }

    public function metadataPath(): string
    {
        return (string) config('delfi_import.metadata_path', $this->cachePath() . '.meta.json');
    }

    /**
     * Downloads a new feed into a temporary file. The caller promotes it only
     * after a complete database sync, so a truncated feed cannot replace the
     * last known-good cache.
     */
    public function download(): array
    {
        $this->ensureDirectory(dirname($this->cachePath()));
        $stored = $this->storedMetadata();
        $headers = [
            'Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.1',
            'User-Agent' => 'Zuzi-Delfi-Importer/1.0',
        ];

        if (is_file($this->cachePath())) {
            if (! empty($stored['etag'])) {
                $headers['If-None-Match'] = (string) $stored['etag'];
            }
            if (! empty($stored['last_modified'])) {
                $headers['If-Modified-Since'] = (string) $stored['last_modified'];
            }
        }

        $temporaryPath = tempnam(dirname($this->cachePath()), '.delfi-feed-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Nije moguće napraviti privremenu datoteku za Delfi feed.');
        }

        $maximumBytes = (int) config('delfi_import.max_feed_bytes', 512 * 1024 * 1024);
        try {
            $response = Http::withOptions([
                    'sink' => $temporaryPath,
                    'connect_timeout' => (int) config('delfi_import.connect_timeout', 20),
                    'allow_redirects' => false,
                    'on_headers' => function ($response) use ($maximumBytes) {
                        $length = trim((string) $response->getHeaderLine('Content-Length'));
                        if (ctype_digit($length) && (int) $length > $maximumBytes) {
                            throw new RuntimeException('Delfi feed je veći od dopuštene veličine.');
                        }
                    },
                    'progress' => function ($downloadTotal, $downloadedBytes) use ($maximumBytes) {
                        if ((int) $downloadedBytes > $maximumBytes) {
                            throw new RuntimeException('Delfi feed je veći od dopuštene veličine.');
                        }
                    },
                ])
                ->timeout((int) config('delfi_import.download_timeout', 1200))
                ->withHeaders($headers)
                ->get($this->feedUrl());

            if ($response->status() === 304) {
                @unlink($temporaryPath);
                $this->assertReadableFile($this->cachePath());

                return array_merge($this->metadata(), [
                    'path' => $this->cachePath(),
                    'temporary' => false,
                    'not_modified' => true,
                ]);
            }

            if (! $response->successful()) {
                throw new RuntimeException('Delfi feed vratio je HTTP ' . $response->status() . '.');
            }

            $bytes = $this->assertDownloadSize($temporaryPath);
            $this->assertRssDocument($temporaryPath);
            $etag = trim((string) $response->header('ETag'));
            $lastModified = trim((string) $response->header('Last-Modified'));

            return [
                'exists' => true,
                'path' => $temporaryPath,
                'temporary' => true,
                'not_modified' => false,
                'bytes' => $bytes,
                'etag' => $etag ?: null,
                'last_modified' => $lastModified ?: null,
                'fingerprint' => hash('sha256', $etag . '|' . $lastModified . '|' . $bytes),
                'downloaded_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $exception) {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw $exception;
        }
    }

    public function promote(array $download, array $syncMetadata = []): array
    {
        $path = (string) ($download['path'] ?? '');
        if (! ($download['temporary'] ?? false) || $path === '') {
            throw new RuntimeException('Nova verzija Delfi feeda nije spremna za pohranu.');
        }

        $this->assertReadableFile($path);
        $this->ensureDirectory(dirname($this->cachePath()));

        if (! rename($path, $this->cachePath())) {
            throw new RuntimeException('Nije moguće spremiti novu verziju Delfi feeda.');
        }

        $metadata = array_merge(
            $download,
            $syncMetadata,
            [
                'path' => $this->cachePath(),
                'temporary' => false,
                'not_modified' => false,
                'synced_at' => now()->toIso8601String(),
            ]
        );
        unset($metadata['exists']);
        $this->writeMetadata($metadata);

        return ['exists' => true] + $metadata;
    }

    public function recordSyncMetadata(array $syncMetadata): void
    {
        $metadata = array_merge($this->storedMetadata(), $syncMetadata, [
            'synced_at' => now()->toIso8601String(),
        ]);

        $this->writeMetadata($metadata);
    }

    public function discard(array $download): void
    {
        if (($download['temporary'] ?? false) && is_file((string) ($download['path'] ?? ''))) {
            @unlink((string) $download['path']);
        }
    }

    public function metadata(): array
    {
        $path = $this->cachePath();
        if (! is_file($path)) {
            return [
                'exists' => false,
                'bytes' => 0,
                'modified_at' => null,
                'staged' => 0,
            ];
        }

        $this->assertReadableFile($path);
        $stored = $this->storedMetadata();

        return array_merge($stored, [
            'exists' => true,
            'bytes' => filesize($path) ?: 0,
            'modified_at' => date('d.m.Y. H:i', filemtime($path) ?: time()),
        ]);
    }

    public function iterate(string $path): Generator
    {
        $this->assertReadableFile($path);
        $reader = new XMLReader();
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            libxml_use_internal_errors($previousErrors);
            throw new RuntimeException('Delfi feed nije moguće otvoriti.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    throw new RuntimeException('DOCTYPE nije dopušten u Delfi feedu.');
                }

                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'item') {
                    continue;
                }

                $xml = $reader->readOuterXml();
                if ($xml === '') {
                    throw new RuntimeException('Neispravan artikl u Delfi feedu.');
                }

                yield $this->normalizer->normalizeItemXml($xml);
            }

            if (libxml_get_errors() !== []) {
                throw new RuntimeException('Delfi feed sadrži neispravan XML.');
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    private function assertDownloadSize(string $path): int
    {
        $bytes = filesize($path);
        $minimum = (int) config('delfi_import.min_feed_bytes', 1024 * 1024);
        $maximum = (int) config('delfi_import.max_feed_bytes', 512 * 1024 * 1024);

        if ($bytes === false || $bytes < $minimum || $bytes > $maximum) {
            throw new RuntimeException('Delfi feed ima neispravnu veličinu.');
        }

        return $bytes;
    }

    private function assertRssDocument(string $path): void
    {
        $reader = new XMLReader();
        if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new RuntimeException('Delfi feed nije moguće otvoriti.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    throw new RuntimeException('DOCTYPE nije dopušten u Delfi feedu.');
                }

                if ($reader->nodeType === XMLReader::ELEMENT) {
                    if ($reader->localName !== 'rss') {
                        throw new RuntimeException('Delfi feed nema očekivani RSS format.');
                    }

                    return;
                }
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException('Delfi feed je prazan.');
    }

    private function storedMetadata(): array
    {
        $path = $this->metadataPath();
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeMetadata(array $metadata): void
    {
        $this->ensureDirectory(dirname($this->metadataPath()));
        $temporaryPath = tempnam(dirname($this->metadataPath()), '.delfi-meta-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Nije moguće spremiti metapodatke Delfi feeda.');
        }

        $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || file_put_contents($temporaryPath, $encoded, LOCK_EX) === false
            || ! rename($temporaryPath, $this->metadataPath())) {
            @unlink($temporaryPath);
            throw new RuntimeException('Nije moguće spremiti metapodatke Delfi feeda.');
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Nije moguće napraviti direktorij za Delfi feed.');
        }
    }

    private function assertReadableFile(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Delfi feed nije pronađen ili nije čitljiv.');
        }
    }
}
