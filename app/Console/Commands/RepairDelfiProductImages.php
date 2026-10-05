<?php

namespace App\Console\Commands;

use App\Models\Back\Catalog\DelfiImportProduct;
use App\Services\Delfi\DelfiImportService;
use Illuminate\Console\Command;
use Throwable;

class RepairDelfiProductImages extends Command
{
    protected $signature = 'images:repair-delfi
        {--product=* : Repair only these Zuzi product IDs}
        {--limit=100 : Maximum number of imported rows to inspect}
        {--refresh-details : Refresh image data from the Delfi API first}
        {--deactivate-missing : Deactivate an article only when the refreshed source has no usable image URL}
        {--apply : Download and store images; without this option the command is a dry run}';

    protected $description = 'Safely repair missing images for imported Delfi products without changing prices, stock or categories.';

    public function handle(DelfiImportService $importService): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 1000) {
            $this->error('Vrijednost --limit mora biti cijeli broj između 1 i 1000.');

            return self::INVALID;
        }

        $productIds = collect($this->option('product'))
            ->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->filter(fn ($id) => $id !== false && $id > 0)
            ->unique()
            ->values();
        if (count($this->option('product')) !== $productIds->count()) {
            $this->error('Svaka --product vrijednost mora biti pozitivan cijeli broj.');

            return self::INVALID;
        }

        $query = DelfiImportProduct::query()
            ->whereNotNull('product_id')
            ->whereNotNull('imported_at')
            ->whereHas('product', function ($query) {
                $query->whereNull('image')->orWhere('image', '');
            });

        if ($productIds->isNotEmpty()) {
            $query->whereIn('product_id', $productIds);
        }

        $sources = $query->orderBy('product_id')->limit($limit)->get();
        if ($sources->isEmpty()) {
            $this->info('Nema povezanih uvezenih Delfi artikala kojima nedostaje slika.');

            return self::SUCCESS;
        }

        $this->table(
            ['Izvor', 'Artikl', 'Izvor slike'],
            $sources->map(fn (DelfiImportProduct $source) => [
                $source->id,
                $source->product_id,
                $this->hasUsableImagePath((string) $source->image_url) ? 'da' : 'nije potvrđen',
            ])->all()
        );

        if (! $this->option('apply')) {
            $this->comment('Probni prikaz: ništa nije promijenjeno. Dodajte --apply za image-only popravak.');

            return self::SUCCESS;
        }

        $stats = [
            'repaired' => 0,
            'deactivated' => 0,
            'unchanged' => 0,
            'unavailable' => 0,
            'failed' => 0,
        ];
        foreach ($sources as $source) {
            try {
                $result = $importService->repairMissingImage(
                    $source,
                    (bool) $this->option('refresh-details'),
                    (bool) $this->option('deactivate-missing')
                );
                $action = (string) ($result['action'] ?? 'failed');
                if (isset($stats[$action])) {
                    $stats[$action]++;
                } else {
                    $stats['failed']++;
                }
                $this->line(sprintf(
                    '%d: %s — %s',
                    $source->product_id,
                    $action,
                    $result['message'] ?? ''
                ));
            } catch (Throwable $exception) {
                $stats['failed']++;
                report($exception);
                $this->warn(sprintf('%d: greška — %s', $source->product_id, $exception->getMessage()));
            }
        }

        $this->info(sprintf(
            'Završeno: %d popravljeno, %d deaktivirano, %d bez promjene, %d bez izvorne slike, %d grešaka.',
            $stats['repaired'],
            $stats['deactivated'],
            $stats['unchanged'],
            $stats['unavailable'],
            $stats['failed']
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function hasUsableImagePath(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '';
    }
}
