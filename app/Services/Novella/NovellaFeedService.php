<?php

namespace App\Services\Novella;

class NovellaFeedService
{
    public function metadata(): array
    {
        $snapshotPath = (string) config('novella_import.snapshot_path');
        $metadataPath = (string) config('novella_import.metadata_path');
        $metadata = [];
        $exists = $snapshotPath !== '' && is_file($snapshotPath);

        if ($metadataPath !== '' && is_file($metadataPath)) {
            $decoded = json_decode((string) file_get_contents($metadataPath), true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return array_merge([
            'path' => $snapshotPath,
            'exists' => $exists,
            'count' => 0,
            'bytes' => $exists ? (filesize($snapshotPath) ?: 0) : 0,
            'modified_at' => $exists
                ? date('d.m.Y. H:i', filemtime($snapshotPath) ?: time())
                : null,
            'synced_at' => null,
            'sha256' => null,
        ], $metadata);
    }
}
