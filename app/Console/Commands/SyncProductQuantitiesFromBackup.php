<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncProductQuantitiesFromBackup extends Command
{
    protected $signature = 'products:sync-quantities-from-backup
                            {--source-db=oldzuzi_backup : Database that contains backup product quantities}
                            {--match=id : Product column used for matching: id, sku, itemid, isbn, ean}
                            {--apply : Write quantity changes to the current database}
                            {--include-positive-targets : Also update products that already have quantity greater than zero}
                            {--examples=20 : Number of candidate examples to show}
                            {--limit=0 : Process only the first N candidates when applying}
                            {--write-csv= : Write all update candidates to a CSV file}
                            {--write-sql= : Write chunked SQL update statements for all candidates}';

    protected $description = 'Compare current product quantities with a backup database and restore missing positive quantities.';

    private const ALLOWED_MATCH_COLUMNS = ['id', 'sku', 'itemid', 'isbn', 'ean'];

    public function handle(): int
    {
        $sourceDb = (string) $this->option('source-db');
        $matchColumn = (string) $this->option('match');
        $targetConnection = DB::connection();
        $targetDb = (string) $targetConnection->getDatabaseName();

        if (! $this->isSafeIdentifier($sourceDb)) {
            $this->error('Invalid --source-db value. Use only letters, numbers and underscores.');

            return self::FAILURE;
        }

        if (! in_array($matchColumn, self::ALLOWED_MATCH_COLUMNS, true)) {
            $this->error('Invalid --match value. Allowed: ' . implode(', ', self::ALLOWED_MATCH_COLUMNS));

            return self::FAILURE;
        }

        if ($sourceDb === $targetDb) {
            $this->error('Source database and target database are the same. Aborting.');

            return self::FAILURE;
        }

        if (! $this->canUseSourceDatabase($sourceDb)) {
            return self::FAILURE;
        }

        if (! $this->canUseTargetDatabase($matchColumn)) {
            return self::FAILURE;
        }

        if (! $this->sourceHasColumns($sourceDb, ['products' => ['quantity', $matchColumn, 'name', 'sku']])) {
            return self::FAILURE;
        }

        try {
            $this->renderSummary($sourceDb, $targetDb, $matchColumn);

            $duplicateSourceKeys = $this->duplicateSourceKeys($sourceDb, $matchColumn);
            $duplicateTargetKeys = $this->duplicateTargetKeys($matchColumn);

            if ($duplicateSourceKeys > 0 || $duplicateTargetKeys > 0) {
                $this->error(sprintf(
                    'Duplicate %s keys found. Source duplicates: %d, target duplicates: %d. Aborting to avoid wrong updates.',
                    $matchColumn,
                    $duplicateSourceKeys,
                    $duplicateTargetKeys
                ));

                return self::FAILURE;
            }

            $candidatesCount = $this->candidateQuery($sourceDb, $matchColumn)->count();
            $this->info(sprintf('Candidates to update: %d', $candidatesCount));

            $this->renderExamples($sourceDb, $matchColumn);

            if ($csvPath = $this->option('write-csv')) {
                $written = $this->writeCsv($sourceDb, $matchColumn, (string) $csvPath);
                $this->info(sprintf('CSV report written: %s (%d rows)', $csvPath, $written));
            }

            if ($sqlPath = $this->option('write-sql')) {
                $written = $this->writeSql($sourceDb, $matchColumn, (string) $sqlPath);
                $this->info(sprintf('SQL patch written: %s (%d rows)', $sqlPath, $written));
            }

            if (! $this->option('apply')) {
                $this->line('Dry run finished. Re-run with --apply to write quantities.');

                return self::SUCCESS;
            }

            $updated = $this->applyUpdates($sourceDb, $matchColumn);
            $this->info(sprintf('Updated products: %d', $updated));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Quantity sync failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function renderSummary(string $sourceDb, string $targetDb, string $matchColumn): void
    {
        $sourcePositive = DB::table($this->sourceTable($sourceDb))
            ->where('quantity', '>', 0)
            ->count();

        $targetPositive = DB::table('products')
            ->where('quantity', '>', 0)
            ->count();

        $matchedSourcePositive = $this->baseJoinedQuery($sourceDb, $matchColumn)
            ->where('source.quantity', '>', 0)
            ->count();

        $targetAlreadyPositive = $this->baseJoinedQuery($sourceDb, $matchColumn)
            ->where('source.quantity', '>', 0)
            ->where('target.quantity', '>', 0)
            ->count();

        $missingInTarget = $this->sourceMissingInTargetCount($sourceDb, $matchColumn);
        $invalidSourceKeys = $this->invalidSourceKeys($sourceDb, $matchColumn);

        $this->line(sprintf('Target database: %s', $targetDb));
        $this->line(sprintf('Source database: %s', $sourceDb));
        $this->line(sprintf('Match column: %s', $matchColumn));
        $this->line(sprintf('Source products with quantity > 0: %d', $sourcePositive));
        $this->line(sprintf('Target products with quantity > 0: %d', $targetPositive));
        $this->line(sprintf('Matched source-positive products: %d', $matchedSourcePositive));
        $this->line(sprintf('Matched target already positive: %d', $targetAlreadyPositive));
        $this->line(sprintf('Source-positive products missing in target: %d', $missingInTarget));
        $this->line(sprintf('Source-positive products with invalid match key: %d', $invalidSourceKeys));
    }

    private function renderExamples(string $sourceDb, string $matchColumn): void
    {
        $examples = max(0, (int) $this->option('examples'));

        if ($examples === 0) {
            return;
        }

        $rows = $this->candidateQuery($sourceDb, $matchColumn)
            ->limit($examples)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $this->table(
            ['target_id', 'source_id', 'match', 'target_qty', 'source_qty', 'target_name', 'source_name'],
            $rows->map(function ($row) {
                return [
                    $row->target_id,
                    $row->source_id,
                    $row->match_value,
                    $row->target_quantity,
                    $row->source_quantity,
                    mb_strimwidth((string) $row->target_name, 0, 48, '...'),
                    mb_strimwidth((string) $row->source_name, 0, 48, '...'),
                ];
            })->all()
        );
    }

    private function applyUpdates(string $sourceDb, string $matchColumn): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $updated = 0;
        $hasUpdatedAt = Schema::hasColumn('products', 'updated_at');
        $hasMarkerColumn = Schema::hasColumn('products', 'stock_restored_from_backup');

        $rows = $this->candidateQuery($sourceDb, $matchColumn)
            ->orderBy('target.id');

        if ($limit > 0) {
            $rows->limit($limit);
        }

        $rows = $rows->get();

        foreach ($rows->chunk(100) as $chunk) {
            $cases = [];
            $ids = [];

            foreach ($chunk as $row) {
                $id = (int) $row->target_id;
                $quantity = max(0, (int) $row->source_quantity);

                $ids[] = $id;
                $cases[] = sprintf('WHEN %d THEN %d', $id, $quantity);
            }

            if (! $ids) {
                continue;
            }

            $update = [
                'quantity' => DB::raw('CASE `id` ' . implode(' ', $cases) . ' ELSE `quantity` END'),
            ];

            if ($hasUpdatedAt) {
                $update['updated_at'] = now();
            }

            if ($hasMarkerColumn) {
                $update['stock_restored_from_backup'] = 1;
            }

            $updated += DB::table('products')
                ->whereIn('id', $ids)
                ->update($update);
        }

        return $updated;
    }

    private function writeCsv(string $sourceDb, string $matchColumn, string $path): int
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV path for writing: ' . $path);
        }

        fputcsv($handle, [
            'target_id',
            'source_id',
            'match_column',
            'match_value',
            'target_sku',
            'source_sku',
            'target_name',
            'source_name',
            'target_quantity',
            'source_quantity',
        ]);

        $written = 0;

        $this->candidateQuery($sourceDb, $matchColumn)
            ->orderBy('target.id')
            ->chunk(1000, function ($rows) use ($handle, $matchColumn, &$written) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->target_id,
                        $row->source_id,
                        $matchColumn,
                        $row->match_value,
                        $row->target_sku,
                        $row->source_sku,
                        $row->target_name,
                        $row->source_name,
                        $row->target_quantity,
                        $row->source_quantity,
                    ]);

                    $written++;
                }
            });

        fclose($handle);

        return $written;
    }

    private function writeSql(string $sourceDb, string $matchColumn, string $path): int
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open SQL path for writing: ' . $path);
        }

        $hasUpdatedAt = Schema::hasColumn('products', 'updated_at');
        $quantityCondition = $this->option('include-positive-targets') ? '1 = 1' : '`quantity` <= 0';
        $written = 0;

        fwrite($handle, "-- Generated by php artisan products:sync-quantities-from-backup\n");
        fwrite($handle, "-- Source database: {$sourceDb}\n");
        fwrite($handle, "-- Match column: {$matchColumn}\n");
        fwrite($handle, "SET @stock_restored_column_exists := (\n");
        fwrite($handle, "    SELECT COUNT(*)\n");
        fwrite($handle, "    FROM INFORMATION_SCHEMA.COLUMNS\n");
        fwrite($handle, "    WHERE TABLE_SCHEMA = DATABASE()\n");
        fwrite($handle, "      AND TABLE_NAME = 'products'\n");
        fwrite($handle, "      AND COLUMN_NAME = 'stock_restored_from_backup'\n");
        fwrite($handle, ");\n");
        fwrite($handle, "SET @add_stock_restored_column_sql := IF(\n");
        fwrite($handle, "    @stock_restored_column_exists = 0,\n");
        fwrite($handle, "    'ALTER TABLE `products` ADD COLUMN `stock_restored_from_backup` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `delivery_24h`',\n");
        fwrite($handle, "    'SELECT ''products.stock_restored_from_backup already exists'' AS info'\n");
        fwrite($handle, ");\n");
        fwrite($handle, "PREPARE add_stock_restored_column_stmt FROM @add_stock_restored_column_sql;\n");
        fwrite($handle, "EXECUTE add_stock_restored_column_stmt;\n");
        fwrite($handle, "DEALLOCATE PREPARE add_stock_restored_column_stmt;\n\n");
        fwrite($handle, "START TRANSACTION;\n\n");

        $this->candidateQuery($sourceDb, $matchColumn)
            ->orderBy('target.id')
            ->chunk(500, function ($rows) use ($handle, $hasUpdatedAt, $quantityCondition, &$written) {
                $cases = [];
                $ids = [];

                foreach ($rows as $row) {
                    $id = (int) $row->target_id;
                    $quantity = max(0, (int) $row->source_quantity);

                    $ids[] = $id;
                    $cases[] = sprintf('WHEN %d THEN %d', $id, $quantity);
                    $written++;
                }

                if (! $ids) {
                    return;
                }

                fwrite($handle, "UPDATE `products`\n");
                fwrite($handle, "SET `quantity` = CASE WHEN {$quantityCondition} THEN CASE `id`\n");

                foreach ($cases as $case) {
                    fwrite($handle, '    ' . $case . "\n");
                }

                fwrite($handle, "    ELSE `quantity`\n");
                fwrite($handle, 'END ELSE `quantity` END');
                fwrite($handle, ",\n    `stock_restored_from_backup` = 1");

                if ($hasUpdatedAt) {
                    fwrite($handle, ",\n    `updated_at` = NOW()");
                }

                fwrite($handle, "\nWHERE `id` IN (" . implode(',', $ids) . ");\n\n");
            });

        fwrite($handle, "COMMIT;\n");
        fclose($handle);

        return $written;
    }

    private function candidateQuery(string $sourceDb, string $matchColumn): Builder
    {
        $query = $this->baseJoinedQuery($sourceDb, $matchColumn)
            ->select([
                'target.id as target_id',
                'source.id as source_id',
                'target.sku as target_sku',
                'source.sku as source_sku',
                'target.name as target_name',
                'source.name as source_name',
                'target.quantity as target_quantity',
                'source.quantity as source_quantity',
            ])
            ->selectRaw($this->wrappedAlias('source', $matchColumn) . ' as match_value')
            ->where('source.quantity', '>', 0)
            ->whereColumn('target.quantity', '<>', 'source.quantity');

        if (! $this->option('include-positive-targets')) {
            $query->where('target.quantity', '<=', 0);
        }

        return $query;
    }

    private function baseJoinedQuery(string $sourceDb, string $matchColumn): Builder
    {
        return DB::table('products as target')
            ->join($this->sourceTable($sourceDb) . ' as source', function ($join) use ($matchColumn) {
                $join->on(
                    $this->aliasedColumn('target', $matchColumn),
                    '=',
                    $this->aliasedColumn('source', $matchColumn)
                );
            })
            ->whereRaw($this->validKeySql('source', $matchColumn))
            ->whereRaw($this->validKeySql('target', $matchColumn));
    }

    private function sourceMissingInTargetCount(string $sourceDb, string $matchColumn): int
    {
        return DB::table($this->sourceTable($sourceDb) . ' as source')
            ->leftJoin('products as target', function ($join) use ($matchColumn) {
                $join->on(
                    $this->aliasedColumn('target', $matchColumn),
                    '=',
                    $this->aliasedColumn('source', $matchColumn)
                );
            })
            ->where('source.quantity', '>', 0)
            ->whereRaw($this->validKeySql('source', $matchColumn))
            ->whereNull('target.id')
            ->count();
    }

    private function duplicateSourceKeys(string $sourceDb, string $matchColumn): int
    {
        return $this->duplicateKeysQuery($this->sourceTable($sourceDb), $matchColumn, true)->count();
    }

    private function duplicateTargetKeys(string $matchColumn): int
    {
        return $this->duplicateKeysQuery('products', $matchColumn)->count();
    }

    private function duplicateKeysQuery(string $table, string $matchColumn, bool $positiveOnly = false): Builder
    {
        $inner = DB::table($table)
            ->select($matchColumn)
            ->whereRaw($this->validKeySql(null, $matchColumn))
            ->groupBy($matchColumn)
            ->havingRaw('COUNT(*) > 1');

        if ($positiveOnly) {
            $inner->where('quantity', '>', 0);
        }

        return DB::query()->fromSub($inner, 'duplicates');
    }

    private function invalidSourceKeys(string $sourceDb, string $matchColumn): int
    {
        return DB::table($this->sourceTable($sourceDb) . ' as source')
            ->where('source.quantity', '>', 0)
            ->whereRaw('NOT (' . $this->validKeySql('source', $matchColumn) . ')')
            ->count();
    }

    private function validKeySql(?string $alias, string $matchColumn): string
    {
        $column = $alias
            ? $this->wrappedAlias($alias, $matchColumn)
            : '`' . $matchColumn . '`';

        if (in_array($matchColumn, ['id', 'itemid'], true)) {
            return $column . ' IS NOT NULL AND ' . $column . ' <> 0';
        }

        return $column . " IS NOT NULL AND TRIM(" . $column . ") <> '' AND TRIM(" . $column . ") <> '0'";
    }

    private function sourceTable(string $sourceDb): string
    {
        return $sourceDb . '.products';
    }

    private function wrappedAlias(string $alias, string $column): string
    {
        return '`' . $alias . '`.`' . $column . '`';
    }

    private function aliasedColumn(string $alias, string $column): string
    {
        return $alias . '.' . $column;
    }

    private function canUseSourceDatabase(string $sourceDb): bool
    {
        $exists = DB::table('information_schema.schemata')
            ->where('schema_name', $sourceDb)
            ->exists();

        if (! $exists) {
            $this->error('Source database does not exist: ' . $sourceDb);

            return false;
        }

        $hasProducts = DB::table('information_schema.tables')
            ->where('table_schema', $sourceDb)
            ->where('table_name', 'products')
            ->exists();

        if (! $hasProducts) {
            $this->error('Source database does not contain products table: ' . $sourceDb);

            return false;
        }

        return true;
    }

    private function canUseTargetDatabase(string $matchColumn): bool
    {
        if (! Schema::hasTable('products')) {
            $this->error('Target database does not contain products table.');

            return false;
        }

        foreach (['id', 'quantity', 'name', 'sku', $matchColumn] as $column) {
            if (! Schema::hasColumn('products', $column)) {
                $this->error('Target products table is missing column: ' . $column);

                return false;
            }
        }

        return true;
    }

    private function sourceHasColumns(string $sourceDb, array $tables): bool
    {
        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                $exists = DB::table('information_schema.columns')
                    ->where('table_schema', $sourceDb)
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->exists();

                if (! $exists) {
                    $this->error(sprintf('Source table %s.%s is missing column: %s', $sourceDb, $table, $column));

                    return false;
                }
            }
        }

        return true;
    }

    private function isSafeIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $value);
    }
}
