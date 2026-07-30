<?php

namespace App\Console\Commands;

use App\Support\ProductImageFileSet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class CleanupProductImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Dry-run is the default. Files are only removed with --delete.
     *
     * @var string
     */
    protected $signature = 'images:cleanup-products
        {--delete : Permanently delete orphaned product image files}
        {--min-age=24 : Ignore unreferenced files newer than this many hours}';

    /**
     * @var string
     */
    protected $description = 'Report or delete product images that are no longer referenced by the database.';

    public function handle(): int
    {
        $minimumAge = filter_var($this->option('min-age'), FILTER_VALIDATE_INT);

        if ($minimumAge === false || $minimumAge < 0) {
            $this->error('The --min-age value must be a non-negative whole number of hours.');

            return self::FAILURE;
        }

        $delete = (bool) $this->option('delete');
        $disk = Storage::disk('products');

        try {
            $root = realpath($disk->path(''));
        } catch (Throwable $exception) {
            $this->error('Unable to resolve the products image directory: ' . $exception->getMessage());

            return self::FAILURE;
        }

        if ($root === false || ! is_dir($root)) {
            $this->info('The products image directory does not exist; nothing to clean.');

            return self::SUCCESS;
        }

        if ($root === DIRECTORY_SEPARATOR || strlen($root) < 10) {
            $this->error('Refusing to scan an unexpectedly broad products image directory: ' . $root);

            return self::FAILURE;
        }

        try {
            $protectedFamilies = $this->loadProtectedFamilies();
        } catch (Throwable $exception) {
            $this->error('Could not read image references from the database. No files were changed.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $cutoff = time() - ($minimumAge * 3600);
        $stats = [
            'scanned_files' => 0,
            'scanned_bytes' => 0,
            'protected_files' => 0,
            'young_files' => 0,
            'candidate_files' => 0,
            'candidate_bytes' => 0,
            'deleted_files' => 0,
            'deleted_bytes' => 0,
            'delete_errors' => 0,
        ];
        $candidateFamilies = [];
        $samples = [];
        $sampleLimit = $this->getOutput()->isVerbose() ? PHP_INT_MAX : 20;
        $rootPrefixLength = strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1;

        $this->line(sprintf(
            'Mode: %s | protected image sets: %s | minimum age: %d hour(s)',
            $delete ? 'DELETE' : 'DRY RUN',
            number_format(count($protectedFamilies)),
            $minimumAge
        ));

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $relativePath = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($file->getPathname(), $rootPrefixLength)
            );
            $family = ProductImageFileSet::familyKeyFromRelativePath($relativePath);

            if ($family === null) {
                continue;
            }

            $size = max(0, (int) $file->getSize());
            $stats['scanned_files']++;
            $stats['scanned_bytes'] += $size;

            if (isset($protectedFamilies[$family])) {
                $stats['protected_files']++;
                continue;
            }

            if ($file->getMTime() > $cutoff) {
                $stats['young_files']++;
                continue;
            }

            $stats['candidate_files']++;
            $stats['candidate_bytes'] += $size;
            $candidateFamilies[$family] = true;

            if (count($samples) < $sampleLimit) {
                $samples[] = [$relativePath, $this->formatBytes($size)];
            }

            if (! $delete) {
                continue;
            }

            try {
                $removed = $disk->delete($relativePath);

                if ($removed && ! $disk->exists($relativePath)) {
                    $stats['deleted_files']++;
                    $stats['deleted_bytes'] += $size;
                } else {
                    $stats['delete_errors']++;
                }
            } catch (Throwable $exception) {
                $stats['delete_errors']++;
                $this->warn(sprintf('Could not delete [%s]: %s', $relativePath, $exception->getMessage()));
            }
        }

        if ($delete) {
            $this->removeEmptyDirectories($root);
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count / size'],
            [
                ['Product image files scanned', number_format($stats['scanned_files'])],
                ['Total product image size', $this->formatBytes($stats['scanned_bytes'])],
                ['Files belonging to referenced sets', number_format($stats['protected_files'])],
                ['Young unreferenced files skipped', number_format($stats['young_files'])],
                ['Orphaned image sets', number_format(count($candidateFamilies))],
                ['Orphaned files', number_format($stats['candidate_files'])],
                ['Recoverable space', $this->formatBytes($stats['candidate_bytes'])],
            ]
        );

        if ($samples !== []) {
            $this->line($delete ? 'Deleted file sample:' : 'Orphaned file sample:');
            $this->table(['Relative path', 'Size'], $samples);

            if (! $this->getOutput()->isVerbose() && $stats['candidate_files'] > count($samples)) {
                $this->line(sprintf(
                    '... %s more file(s) omitted; run with -v to list every candidate.',
                    number_format($stats['candidate_files'] - count($samples))
                ));
            }
        }

        if ($delete) {
            $this->info(sprintf(
                'Deleted %s file(s), freeing %s.',
                number_format($stats['deleted_files']),
                $this->formatBytes($stats['deleted_bytes'])
            ));

            if ($stats['delete_errors'] > 0) {
                $this->error(sprintf('%s file(s) could not be deleted.', number_format($stats['delete_errors'])));

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->comment('Dry run only: no files were changed. Re-run with --delete after reviewing this report.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function loadProtectedFamilies(): array
    {
        $families = [];

        foreach (['products', 'product_images'] as $table) {
            foreach (DB::table($table)->whereNotNull('image')->select(['id', 'image'])->orderBy('id')->cursor() as $row) {
                $family = ProductImageFileSet::familyKeyFromStoredPath($row->image);

                if ($family !== null) {
                    $families[$family] = true;
                }
            }
        }

        return $families;
    }

    private function removeEmptyDirectories(string $root): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (! $item->isDir() || $item->isLink()) {
                continue;
            }

            $directory = $item->getPathname();
            $contents = new \FilesystemIterator($directory);

            if (! $contents->valid()) {
                @rmdir($directory);
            }
        }
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
