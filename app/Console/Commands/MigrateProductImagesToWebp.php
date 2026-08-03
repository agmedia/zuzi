<?php

namespace App\Console\Commands;

use App\Support\ProductImageFileSet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateProductImagesToWebp extends Command
{
    /**
     * @var string
     */
    protected $signature = 'images:migrate-products-to-webp
        {--apply : Update database paths and delete redundant JPG files}';

    /**
     * @var string
     */
    protected $description = 'Report or migrate product image references to WebP and remove matching JPG copies.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $disk = Storage::disk('products');
        $stats = [
            'references' => 0,
            'already_webp' => 0,
            'eligible' => 0,
            'missing_webp' => 0,
            'other' => 0,
        ];
        $eligibleIds = [
            'products' => ['jpg' => [], 'jpeg' => []],
            'product_images' => ['jpg' => [], 'jpeg' => []],
        ];
        $candidateFamilies = [];

        try {
            foreach (array_keys($eligibleIds) as $table) {
                DB::table($table)
                    ->whereNotNull('image')
                    ->select(['id', 'image'])
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use (
                        $disk,
                        $table,
                        &$candidateFamilies,
                        &$eligibleIds,
                        &$stats
                    ): void {
                        foreach ($rows as $row) {
                            $stats['references']++;
                            $relativePath = ProductImageFileSet::relativePath($row->image);
                            $family = $relativePath === null
                                ? null
                                : ProductImageFileSet::familyKeyFromRelativePath($relativePath);

                            if ($relativePath === null || $family === null) {
                                $stats['other']++;
                                continue;
                            }

                            $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));

                            if ($extension === 'webp') {
                                $stats['already_webp']++;

                                if ($disk->exists($family . '.webp')) {
                                    $candidateFamilies[$family] = true;
                                }

                                continue;
                            }

                            if (! in_array($extension, ['jpg', 'jpeg'], true)) {
                                $stats['other']++;
                                continue;
                            }

                            if (! $disk->exists($family . '.webp')) {
                                $stats['missing_webp']++;
                                continue;
                            }

                            $stats['eligible']++;
                            $eligibleIds[$table][$extension][] = (int) $row->id;
                            $candidateFamilies[$family] = true;
                        }
                    });
            }
        } catch (Throwable $exception) {
            $this->error('Could not inspect product image references. No files were changed.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        [$jpgFiles, $jpgBytes] = $this->measureJpgCopies(array_keys($candidateFamilies));

        $this->line(sprintf('Mode: %s', $apply ? 'APPLY' : 'DRY RUN'));
        $this->table(
            ['Metric', 'Count / size'],
            [
                ['Database image references scanned', number_format($stats['references'])],
                ['References already using WebP', number_format($stats['already_webp'])],
                ['JPG references ready to migrate', number_format($stats['eligible'])],
                ['JPG references missing a WebP file', number_format($stats['missing_webp'])],
                ['Other/outside product paths', number_format($stats['other'])],
                ['Redundant JPG files', number_format($jpgFiles)],
                ['Recoverable JPG space', $this->formatBytes($jpgBytes)],
            ]
        );

        if (! $apply) {
            $this->comment('Dry run only: no database rows or files were changed.');
            $this->comment('Re-run with --apply after reviewing this report.');

            return self::SUCCESS;
        }

        try {
            $updated = $this->updateReferences($eligibleIds);
            $webpFamilies = $this->loadWebpReferencedFamilies();
        } catch (Throwable $exception) {
            $this->error('The database migration did not finish. JPG files were not deleted.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $deletedFiles = 0;
        $deletedBytes = 0;
        $deleteErrors = 0;

        foreach (array_keys($webpFamilies) as $family) {
            if (! $disk->exists($family . '.webp')) {
                continue;
            }

            foreach ([$family . '.jpg', $family . '.jpeg'] as $jpgPath) {
                if (! $disk->exists($jpgPath)) {
                    continue;
                }

                $size = max(0, (int) $disk->size($jpgPath));

                try {
                    if ($disk->delete($jpgPath) && ! $disk->exists($jpgPath)) {
                        $deletedFiles++;
                        $deletedBytes += $size;
                    } else {
                        $deleteErrors++;
                    }
                } catch (Throwable $exception) {
                    $deleteErrors++;
                    $this->warn(sprintf('Could not delete [%s]: %s', $jpgPath, $exception->getMessage()));
                }
            }
        }

        $this->info(sprintf(
            'Migrated %s database reference(s) to WebP.',
            number_format($updated)
        ));
        $this->info(sprintf(
            'Deleted %s redundant JPG file(s), freeing %s.',
            number_format($deletedFiles),
            $this->formatBytes($deletedBytes)
        ));

        if ($deleteErrors > 0) {
            $this->error(sprintf('%s JPG file(s) could not be deleted.', number_format($deleteErrors)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, array<string, array<int, int>>> $eligibleIds
     */
    private function updateReferences(array $eligibleIds): int
    {
        $updated = 0;

        foreach ($eligibleIds as $table => $extensions) {
            foreach ($extensions as $extension => $ids) {
                $suffixLength = strlen($extension);

                foreach (array_chunk($ids, 500) as $chunk) {
                    $updated += DB::table($table)
                        ->whereIn('id', $chunk)
                        ->whereRaw('LOWER(image) LIKE ?', ['%.' . $extension])
                        ->update([
                            'image' => DB::raw(sprintf(
                                "CONCAT(LEFT(image, CHAR_LENGTH(image) - %d), 'webp')",
                                $suffixLength
                            )),
                        ]);
                }
            }
        }

        return $updated;
    }

    /**
     * @return array<string, true>
     */
    private function loadWebpReferencedFamilies(): array
    {
        $families = [];

        foreach (['products', 'product_images'] as $table) {
            foreach (DB::table($table)->whereNotNull('image')->select(['id', 'image'])->orderBy('id')->cursor() as $row) {
                $relativePath = ProductImageFileSet::relativePath($row->image);

                if ($relativePath === null || strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'webp') {
                    continue;
                }

                $family = ProductImageFileSet::familyKeyFromRelativePath($relativePath);

                if ($family !== null) {
                    $families[$family] = true;
                }
            }
        }

        return $families;
    }

    /**
     * @param array<int, string> $families
     * @return array{0: int, 1: int}
     */
    private function measureJpgCopies(array $families): array
    {
        $disk = Storage::disk('products');
        $files = 0;
        $bytes = 0;

        foreach ($families as $family) {
            foreach ([$family . '.jpg', $family . '.jpeg'] as $jpgPath) {
                if (! $disk->exists($jpgPath)) {
                    continue;
                }

                $files++;
                $bytes += max(0, (int) $disk->size($jpgPath));
            }
        }

        return [$files, $bytes];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $value = max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return $unit === 0
            ? sprintf('%d %s', $value, $units[$unit])
            : sprintf('%.2f %s', $value, $units[$unit]);
    }
}
