<?php

namespace Tests\Feature;

use App\Http\Controllers\Front\HomeController;
use App\Models\Front\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HomepageDescriptionSnapshotTest extends TestCase
{
    private string $snapshotPath;

    private ?string $snapshotBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshotPath = storage_path('app/homepage-description-snapshot.json');

        if (File::exists($this->snapshotPath)) {
            $this->snapshotBackup = File::get($this->snapshotPath);
        }
    }

    protected function tearDown(): void
    {
        if ($this->snapshotBackup !== null) {
            File::ensureDirectoryExists(dirname($this->snapshotPath));
            File::put($this->snapshotPath, $this->snapshotBackup);
        } elseif (File::exists($this->snapshotPath)) {
            File::delete($this->snapshotPath);
        }

        parent::tearDown();
    }

    public function test_homepage_snapshot_is_rebuilt_as_unresolved_description_when_legacy_html_snapshot_exists()
    {
        File::ensureDirectoryExists(dirname($this->snapshotPath));
        File::put($this->snapshotPath, json_encode([
            'version' => 1,
            'signature' => 'legacy-signature',
            'html' => '<section>stari widget html</section>',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $page = new Page([
            'id' => 1,
            'description' => '++novo++',
            'short_description' => 'kratki opis',
            'updated_at' => Carbon::parse('2026-04-17 10:00:00'),
        ]);

        $controller = new HomeController();

        $resolved = \Closure::bind(function (Page $page): string {
            return $this->resolveHomepageDescriptionSnapshot($page);
        }, $controller, HomeController::class)($page);

        $this->assertSame('<!--home-sales-widget-->++novo++', $resolved);

        $snapshot = json_decode((string) File::get($this->snapshotPath), true);

        $this->assertSame(2, $snapshot['version'] ?? null);
        $this->assertSame('<!--home-sales-widget-->++novo++', $snapshot['description'] ?? null);
        $this->assertArrayNotHasKey('html', $snapshot);
    }
}
