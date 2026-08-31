<?php

namespace Tests\Unit;

use App\Services\Znanje\ZnanjeFeedService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ZnanjeFeedServiceTest extends TestCase
{
    private string $snapshotPath;

    private string $metadataPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshotPath = storage_path('framework/testing/znanje-feed-service.json');
        $this->metadataPath = storage_path('framework/testing/znanje-feed-service.meta.json');
        File::delete([$this->snapshotPath, $this->metadataPath]);
        config([
            'znanje_import.snapshot_path' => $this->snapshotPath,
            'znanje_import.metadata_path' => $this->metadataPath,
        ]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->snapshotPath, $this->metadataPath]);
        parent::tearDown();
    }

    public function test_metadata_accepts_only_a_snapshot_with_matching_hash_bytes_and_count(): void
    {
        $snapshot = '{"synced_at":"2026-08-31T10:00:00+02:00","count":1,'
            . '"root_totals":{"Knjige":1,"Strane knjige":0},'
            . '"items":[{"external_id":"100"}]}';
        file_put_contents($this->snapshotPath, $snapshot);
        $validMetadata = [
            'synced_at' => '2026-08-31T10:00:00+02:00',
            'count' => 1,
            'root_totals' => ['Knjige' => 1, 'Strane knjige' => 0],
            'bytes' => strlen($snapshot),
            'sha256' => hash('sha256', $snapshot),
        ];
        file_put_contents($this->metadataPath, json_encode($validMetadata));

        $service = app(ZnanjeFeedService::class);
        $metadata = $service->metadata();
        $this->assertTrue($metadata['exists']);
        $this->assertTrue($metadata['valid']);
        $this->assertNull($metadata['warning']);
        $this->assertSame(1, $metadata['count']);
        $this->assertSame('100', $service->snapshot()['items'][0]['external_id']);

        foreach ([
            array_merge($validMetadata, ['bytes' => strlen($snapshot) + 1]),
            array_merge($validMetadata, ['sha256' => str_repeat('0', 64)]),
            array_merge($validMetadata, ['count' => 2]),
        ] as $invalidMetadata) {
            file_put_contents($this->metadataPath, json_encode($invalidMetadata));
            $metadata = $service->metadata();
            $this->assertFalse($metadata['exists']);
            $this->assertFalse($metadata['valid']);
            $this->assertNotNull($metadata['warning']);
            $this->assertSame([], $service->snapshot());
        }
    }

    public function test_an_orphan_snapshot_is_not_reported_as_ready(): void
    {
        file_put_contents($this->snapshotPath, '{"count":0,"items":[]}');

        $metadata = app(ZnanjeFeedService::class)->metadata();

        $this->assertTrue($metadata['snapshot_exists']);
        $this->assertFalse($metadata['exists']);
        $this->assertFalse($metadata['valid']);
        $this->assertNotNull($metadata['warning']);
    }

    public function test_metadata_rejects_a_snapshot_whose_declared_count_hides_extra_items(): void
    {
        $snapshot = '{"synced_at":"2026-08-31T10:00:00+02:00","count":1,'
            . '"root_totals":{"Knjige":1,"Strane knjige":0},'
            . '"items":[{"external_id":"100"},{"external_id":"101"}]}';
        file_put_contents($this->snapshotPath, $snapshot);
        file_put_contents($this->metadataPath, json_encode([
            'synced_at' => '2026-08-31T10:00:00+02:00',
            'count' => 1,
            'root_totals' => ['Knjige' => 1, 'Strane knjige' => 0],
            'bytes' => strlen($snapshot),
            'sha256' => hash('sha256', $snapshot),
        ]));

        $metadata = app(ZnanjeFeedService::class)->metadata();

        $this->assertFalse($metadata['exists']);
        $this->assertFalse($metadata['valid']);
        $this->assertNotNull($metadata['warning']);
        $this->assertSame([], app(ZnanjeFeedService::class)->snapshot());
    }
}
