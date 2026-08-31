<?php

namespace App\Services\Znanje;

class ZnanjeFeedService
{
    public function metadata(): array
    {
        $snapshotPath = (string) config('znanje_import.snapshot_path');
        $metadataPath = (string) config('znanje_import.metadata_path');
        $snapshotExists = $snapshotPath !== '' && is_file($snapshotPath);
        $defaults = [
            'path' => $snapshotPath,
            'exists' => false,
            'snapshot_exists' => $snapshotExists,
            'valid' => false,
            'warning' => null,
            'count' => 0,
            'bytes' => $snapshotExists ? (filesize($snapshotPath) ?: 0) : 0,
            'modified_at' => $snapshotExists
                ? date('d.m.Y. H:i', filemtime($snapshotPath) ?: time())
                : null,
            'synced_at' => null,
            'sha256' => null,
            'root_totals' => [],
        ];
        if (! $snapshotExists) {
            return $defaults;
        }

        $metadata = null;
        if ($metadataPath !== '' && is_file($metadataPath)) {
            $decoded = json_decode((string) file_get_contents($metadataPath), true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }
        $snapshot = $this->inspectSnapshot($snapshotPath);
        $actualBytes = $snapshot['bytes'];
        $actualHash = $snapshot['sha256'];
        $snapshotCount = $snapshot['declared_count'];
        $actualCount = $snapshot['actual_count'];
        $metadataCount = is_array($metadata) && isset($metadata['count']) && is_numeric($metadata['count'])
            ? (int) $metadata['count']
            : null;
        $metadataBytes = is_array($metadata) && isset($metadata['bytes']) && is_numeric($metadata['bytes'])
            ? (int) $metadata['bytes']
            : null;
        $metadataHash = is_array($metadata) && isset($metadata['sha256'])
            ? strtolower(trim((string) $metadata['sha256']))
            : '';
        $valid = $actualBytes !== null
            && $actualHash !== null
            && $snapshotCount !== null
            && $actualCount !== null
            && $snapshotCount === $actualCount
            && $metadataCount !== null
            && $metadataBytes === $actualBytes
            && $metadataCount === $snapshotCount
            && preg_match('/\A[a-f0-9]{64}\z/D', $metadataHash) === 1
            && hash_equals($metadataHash, $actualHash);

        return array_merge($defaults, [
            'exists' => $valid,
            'valid' => $valid,
            'warning' => $valid
                ? null
                : 'Lokalna Znanje snimka i njezini kontrolni podaci ne podudaraju se.',
            'count' => $metadataCount ?? $snapshotCount ?? 0,
            'bytes' => $actualBytes ?? 0,
            'synced_at' => is_array($metadata) && is_scalar($metadata['synced_at'] ?? null)
                ? (string) $metadata['synced_at']
                : null,
            'sha256' => $actualHash,
            'root_totals' => is_array($metadata['root_totals'] ?? null)
                ? $metadata['root_totals']
                : [],
        ]);
    }

    public function snapshot(): array
    {
        if (! $this->metadata()['valid']) {
            return [];
        }
        $path = (string) config('znanje_import.snapshot_path');
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function inspectSnapshot(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [
                'bytes' => null,
                'sha256' => null,
                'declared_count' => null,
                'actual_count' => null,
            ];
        }
        $hash = hash_init('sha256');
        $bytes = 0;
        $prefix = '';
        $actualCount = 0;
        $needle = '"external_id":';
        $overlap = '';
        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 64 * 1024);
                if ($chunk === false) {
                    return [
                        'bytes' => null,
                        'sha256' => null,
                        'declared_count' => null,
                        'actual_count' => null,
                    ];
                }
                if ($chunk === '') {
                    continue;
                }
                $bytes += strlen($chunk);
                hash_update($hash, $chunk);
                if (strlen($prefix) < 8192) {
                    $prefix .= substr($chunk, 0, 8192 - strlen($prefix));
                }

                $buffer = $overlap . $chunk;
                $actualCount += substr_count($buffer, $needle);
                $keep = min(strlen($needle) - 1, strlen($buffer));
                $overlap = $keep > 0 ? substr($buffer, -$keep) : '';
            }
        } finally {
            fclose($handle);
        }

        return [
            'bytes' => $bytes,
            'sha256' => hash_final($hash),
            'declared_count' => preg_match('/"count":([0-9]+)/', $prefix, $matches) === 1
                ? (int) $matches[1]
                : null,
            // Every normalized snapshot item has this exact JSON key once.
            // Quotes occurring inside descriptions are escaped, so they do
            // not match the raw token and cannot inflate the count.
            'actual_count' => $actualCount,
        ];
    }
}
