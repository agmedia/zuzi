<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$commit = in_array('--commit', $argv, true);
$excelPath = $root . '/public/KASA_WEBSHOP_SPOJ_SQL.xlsx';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--excel=')) {
        $excelPath = substr($arg, 8);
    }
}

function live_cell_string($value): string
{
    if ($value instanceof RichText) {
        $value = $value->getPlainText();
    }

    if ($value === null) {
        return '';
    }

    if (is_int($value)) {
        return (string) $value;
    }

    if (is_float($value)) {
        return floor($value) === $value ? sprintf('%.0f', $value) : trim((string) $value);
    }

    return trim((string) $value);
}

function live_sku($value): ?string
{
    $value = live_cell_string($value);

    if (preg_match('/^\d+\.0+$/', $value)) {
        $value = preg_replace('/\.0+$/', '', $value) ?: $value;
    }

    return $value === '' ? null : trim($value);
}

function live_numeric_code_key($value): ?string
{
    $value = live_sku($value);

    if ($value === null || ! preg_match('/^\d+$/', $value)) {
        return null;
    }

    $value = ltrim($value, '0');

    return $value === '' ? '0' : $value;
}

function live_barcode($value): ?string
{
    $value = strtoupper(live_cell_string($value));

    if ($value === '') {
        return null;
    }

    $value = str_replace(['ISBN-13', 'ISBN13', 'ISBN-10', 'ISBN10', 'ISBN', ':'], '', $value);
    $value = preg_replace('/[^0-9X]/', '', $value) ?: '';

    return $value === '' ? null : $value;
}

function live_isbn13_valid(string $isbn): bool
{
    if (! preg_match('/^(978|979)\d{10}$/', $isbn)) {
        return false;
    }

    $sum = 0;

    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
    }

    return ((10 - ($sum % 10)) % 10) === (int) $isbn[12];
}

function live_isbn10_valid(string $isbn): bool
{
    if (! preg_match('/^\d{9}[\dX]$/', $isbn)) {
        return false;
    }

    $sum = 0;

    for ($i = 0; $i < 10; $i++) {
        $sum += ($isbn[$i] === 'X' ? 10 : (int) $isbn[$i]) * (10 - $i);
    }

    return $sum % 11 === 0;
}

function live_isbn10_to_13(string $isbn): string
{
    $base = '978' . substr($isbn, 0, 9);
    $sum = 0;

    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $base[$i] * ($i % 2 === 0 ? 1 : 3);
    }

    return $base . ((10 - ($sum % 10)) % 10);
}

function live_isbn_key($value): ?string
{
    $isbn = live_barcode($value);

    if ($isbn === null) {
        return null;
    }

    if (strlen($isbn) === 13 && live_isbn13_valid($isbn)) {
        return $isbn;
    }

    if (strlen($isbn) === 10 && live_isbn10_valid($isbn)) {
        return live_isbn10_to_13($isbn);
    }

    return null;
}

function live_itemid($value): ?int
{
    $value = live_cell_string($value);

    if (preg_match('/^\d+\.0+$/', $value)) {
        $value = preg_replace('/\.0+$/', '', $value) ?: $value;
    }

    if ($value === '' || ! ctype_digit($value)) {
        return null;
    }

    $itemid = (int) $value;

    return $itemid > 0 ? $itemid : null;
}

function live_name_key($value): ?string
{
    $value = preg_replace('/\s+/u', ' ', live_cell_string($value)) ?: '';
    $value = trim($value);

    return $value === '' ? null : mb_strtolower($value, 'UTF-8');
}

function live_tokens($value): array
{
    $value = mb_strtolower(live_cell_string($value), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

    if ($ascii !== false) {
        $value = $ascii;
    }

    $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?: '';
    $tokens = preg_split('/\s+/', trim($value)) ?: [];
    $tokens = array_filter($tokens, fn ($token) => strlen($token) >= 2 && ! in_array($token, ['tu', 'id', 'novo', 'kiosk'], true));

    return array_values(array_unique($tokens));
}

function live_choose_by_name(array $candidates, string $productName): ?object
{
    $productTokens = live_tokens($productName);

    if (count($productTokens) < 2) {
        return null;
    }

    $productTokenSet = array_flip($productTokens);
    $ranked = [];

    foreach ($candidates as $candidate) {
        $itemTokens = live_tokens($candidate->item_name ?? '');

        if (count($itemTokens) < 2) {
            continue;
        }

        $common = 0;

        foreach ($itemTokens as $token) {
            if (isset($productTokenSet[$token])) {
                $common++;
            }
        }

        $ranked[] = [
            'candidate' => $candidate,
            'common' => $common,
            'coverage' => $common / count($itemTokens),
        ];
    }

    usort($ranked, fn ($a, $b) => [$b['coverage'], $b['common']] <=> [$a['coverage'], $a['common']]);

    if (! $ranked) {
        return null;
    }

    $eligible = array_values(array_filter($ranked, fn ($row) => $row['coverage'] >= 0.75 && $row['common'] >= 2));
    $eligibleWithValidIsbn = array_values(array_filter($eligible, fn ($row) => live_isbn_key($row['candidate']->item_barcode ?? null) !== null));

    if (count($eligibleWithValidIsbn) === 1) {
        return $eligibleWithValidIsbn[0]['candidate'];
    }

    $best = $ranked[0];
    $second = $ranked[1] ?? null;

    if ($best['coverage'] < 0.75 || $best['common'] < 2) {
        return null;
    }

    if ($second && $second['coverage'] >= 0.75 && abs($best['coverage'] - $second['coverage']) < 0.15 && ($best['common'] - $second['common']) < 2) {
        return null;
    }

    return $best['candidate'];
}

function live_has_itemid($value): bool
{
    return $value !== null && (int) $value > 0;
}

function live_counter(array &$summary, string $key, int $amount = 1): void
{
    $summary[$key] = ($summary[$key] ?? 0) + $amount;
}

function live_sample(array &$examples, string $key, array $row, int $limit = 10): void
{
    if (! isset($examples[$key])) {
        $examples[$key] = [];
    }

    if (count($examples[$key]) < $limit) {
        $examples[$key][] = $row;
    }
}

function live_apply_updates(array $updates, bool $commit): int
{
    if (! $commit || ! $updates) {
        return 0;
    }

    $applied = 0;

    DB::transaction(function () use ($updates, &$applied): void {
        $now = now();

        foreach ($updates as $productId => $itemid) {
            DB::table('products')
                ->where('id', $productId)
                ->whereNull('itemid')
                ->update([
                    'itemid' => $itemid,
                    'updated_at' => $now,
                ]);

            $applied++;
        }
    });

    return $applied;
}

$summary = [
    'mode' => $commit ? 'commit' : 'dry-run',
    'excel_path' => $excelPath,
    'products_before' => DB::table('products')->count(),
    'missing_itemid_before' => DB::table('products')->whereNull('itemid')->count(),
];
$examples = [];

$products = DB::table('products')->select('id', 'sku', 'name', 'isbn', 'itemid')->get();
$productsBySku = [];
$productsByName = [];
$itemidOwners = [];
$virtualItemids = [];

foreach ($products as $product) {
    $sku = live_sku($product->sku);
    $name = live_name_key($product->name);
    $virtualItemids[(int) $product->id] = live_has_itemid($product->itemid) ? (int) $product->itemid : null;

    if ($sku !== null) {
        $productsBySku[$sku][] = $product;
    }

    if ($name !== null) {
        $productsByName[$name][] = $product;
    }

    if (live_has_itemid($product->itemid)) {
        $itemidOwners[(int) $product->itemid] = (int) $product->id;
    }
}

$excelUpdates = [];

if (is_file($excelPath)) {
    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $reader->setLoadSheetsOnly(['Spoj']);

    $spreadsheet = $reader->load($excelPath);
    $sheet = $spreadsheet->getSheetByName('Spoj') ?: $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestDataRow();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    $headers = [];

    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $headers[live_cell_string($sheet->getCell([$col, 1])->getValue())] = $col;
    }

    foreach (['CARTIKL', 'BC (barkod)', 'Webshop SKU', 'Webshop naziv'] as $header) {
        if (! isset($headers[$header])) {
            fwrite(STDERR, "Missing Excel header: {$header}\n");
            exit(1);
        }
    }

    $rowMatches = [];

    for ($row = 2; $row <= $highestRow; $row++) {
        $itemid = live_itemid($sheet->getCell([$headers['CARTIKL'], $row])->getValue());
        $sku = live_sku($sheet->getCell([$headers['Webshop SKU'], $row])->getValue());
        $name = live_cell_string($sheet->getCell([$headers['Webshop naziv'], $row])->getValue());
        $nameKey = live_name_key($name);
        $isbnKey = live_isbn_key($sheet->getCell([$headers['BC (barkod)'], $row])->getValue());

        if (! $itemid) {
            live_counter($summary, 'excel_skipped_missing_itemid');
            continue;
        }

        if (! $sku && ! $nameKey) {
            live_counter($summary, 'excel_skipped_missing_match_keys');
            continue;
        }

        $matchedProduct = null;
        $method = null;

        if ($sku && isset($productsBySku[$sku])) {
            $candidates = $productsBySku[$sku];

            if (count($candidates) === 1) {
                $matchedProduct = $candidates[0];
                $method = 'sku';
            } elseif ($nameKey) {
                $named = array_values(array_filter($candidates, fn ($product) => live_name_key($product->name) === $nameKey));

                if (count($named) === 1) {
                    $matchedProduct = $named[0];
                    $method = 'sku+name';
                }
            }
        }

        if (! $matchedProduct && $nameKey && isset($productsByName[$nameKey]) && count($productsByName[$nameKey]) === 1) {
            $matchedProduct = $productsByName[$nameKey][0];
            $method = 'name';
        }

        if (! $matchedProduct) {
            live_counter($summary, 'excel_skipped_no_safe_product_match');
            continue;
        }

        $productId = (int) $matchedProduct->id;

        if ($virtualItemids[$productId] !== null) {
            live_counter($summary, 'excel_skipped_existing_itemid');
            continue;
        }

        $rowMatches[$productId]['product'] = $matchedProduct;
        $rowMatches[$productId]['rows'][] = [
            'row' => $row,
            'itemid' => $itemid,
            'isbn_key' => $isbnKey,
            'method' => $method,
        ];
    }

    $proposed = [];

    foreach ($rowMatches as $productId => $match) {
        $product = $match['product'];
        $candidateRows = $match['rows'];
        $skuIsbnKey = live_isbn_key($product->sku);
        $existingIsbnKey = live_isbn_key($product->isbn);

        if ($skuIsbnKey !== null) {
            $isbnRows = array_values(array_filter($candidateRows, fn ($row) => $row['isbn_key'] === $skuIsbnKey));
            if ($isbnRows) {
                $candidateRows = $isbnRows;
            }
        } elseif ($existingIsbnKey !== null) {
            $isbnRows = array_values(array_filter($candidateRows, fn ($row) => $row['isbn_key'] === $existingIsbnKey));
            if ($isbnRows) {
                $candidateRows = $isbnRows;
            }
        }

        $itemids = array_values(array_unique(array_map(fn ($row) => $row['itemid'], $candidateRows)));

        if (count($itemids) !== 1) {
            live_counter($summary, 'excel_skipped_itemid_conflict');
            live_sample($examples, 'excel_itemid_conflict', [
                'product_id' => $productId,
                'sku' => $product->sku,
                'name' => $product->name,
                'itemids' => $itemids,
            ]);
            continue;
        }

        $itemid = (int) $itemids[0];

        if (isset($itemidOwners[$itemid]) && $itemidOwners[$itemid] !== $productId) {
            live_counter($summary, 'excel_skipped_itemid_already_used');
            continue;
        }

        $excelUpdates[$productId] = $itemid;
        $proposed[$itemid][] = $productId;
    }

    foreach ($excelUpdates as $productId => $itemid) {
        $owners = array_values(array_unique($proposed[$itemid] ?? []));

        if (count($owners) > 1) {
            unset($excelUpdates[$productId]);
            live_counter($summary, 'excel_skipped_import_itemid_conflict');
        }
    }
} else {
    $summary['excel_missing'] = true;
}

$summary['excel_itemid_updates'] = count($excelUpdates);
$summary['excel_sample_updates'] = array_slice(array_map(function ($productId, $itemid) use ($products): array {
    $product = $products->firstWhere('id', $productId);

    return [
        'product_id' => $productId,
        'sku' => $product->sku ?? null,
        'name' => $product->name ?? null,
        'new_itemid' => $itemid,
    ];
}, array_keys($excelUpdates), $excelUpdates), 0, 10);
$summary['excel_applied'] = live_apply_updates($excelUpdates, $commit);

foreach ($excelUpdates as $productId => $itemid) {
    $virtualItemids[$productId] = $itemid;
    $itemidOwners[$itemid] = $productId;
}

$pelionUpdates = [];

if (Schema::hasTable('pelion_items')) {
    $pelionItems = DB::table('pelion_items')
        ->select('item_id', 'item_code', 'item_barcode', 'item_name')
        ->get();
    $itemsByCode = [];
    $itemsByNumericCode = [];
    $itemsByIsbnKey = [];

    foreach ($pelionItems as $item) {
        $code = live_sku($item->item_code);
        $numericCode = live_numeric_code_key($item->item_code);
        $isbnKey = live_isbn_key($item->item_barcode);

        if ($code !== null) {
            $itemsByCode[$code][] = $item;
        }

        if ($numericCode !== null) {
            $itemsByNumericCode[$numericCode][] = $item;
        }

        if ($isbnKey !== null) {
            $itemsByIsbnKey[$isbnKey][] = $item;
        }
    }

    $proposed = [];

    foreach ($products as $product) {
        $productId = (int) $product->id;

        if ($virtualItemids[$productId] !== null) {
            continue;
        }

        $sku = live_sku($product->sku);
        $numericSku = live_numeric_code_key($product->sku);
        $isbnKey = live_isbn_key($product->isbn);
        $codeCandidates = $sku !== null ? ($itemsByCode[$sku] ?? []) : [];
        $codeMethod = 'code';

        if (! $codeCandidates && $numericSku !== null) {
            $codeCandidates = $itemsByNumericCode[$numericSku] ?? [];
            $codeMethod = 'code_numeric';
        }

        $isbnCandidates = $isbnKey !== null ? ($itemsByIsbnKey[$isbnKey] ?? []) : [];
        $chosen = null;

        if ($codeCandidates && $isbnCandidates) {
            $isbnCandidateIds = array_flip(array_map(fn ($item) => (int) $item->item_id, $isbnCandidates));
            $intersection = array_values(array_filter($codeCandidates, fn ($item) => isset($isbnCandidateIds[(int) $item->item_id])));

            if (count($intersection) === 1) {
                $chosen = $intersection[0];
            }
        }

        if (! $chosen && count($codeCandidates) === 1) {
            $chosen = $codeCandidates[0];
        }

        if (! $chosen && count($isbnCandidates) === 1) {
            $chosen = $isbnCandidates[0];
        }

        if (! $chosen && $codeCandidates) {
            $chosen = live_choose_by_name($codeCandidates, $product->name ?? '');
        }

        if (! $chosen && $isbnCandidates) {
            $chosen = live_choose_by_name($isbnCandidates, $product->name ?? '');
        }

        if (! $chosen) {
            live_counter($summary, ($codeCandidates || $isbnCandidates) ? 'pelion_skipped_ambiguous' : 'pelion_skipped_no_match');
            continue;
        }

        $itemid = (int) $chosen->item_id;

        if (isset($itemidOwners[$itemid]) && $itemidOwners[$itemid] !== $productId) {
            live_counter($summary, 'pelion_skipped_itemid_already_used');
            continue;
        }

        $pelionUpdates[$productId] = $itemid;
        $proposed[$itemid][] = $productId;
        live_counter($summary, 'pelion_matched_' . $codeMethod);
    }

    foreach ($pelionUpdates as $productId => $itemid) {
        $owners = array_values(array_unique($proposed[$itemid] ?? []));

        if (count($owners) > 1) {
            unset($pelionUpdates[$productId]);
            live_counter($summary, 'pelion_skipped_import_itemid_conflict');
        }
    }
} else {
    $summary['pelion_items_missing'] = true;
}

$summary['pelion_itemid_updates'] = count($pelionUpdates);
$summary['pelion_sample_updates'] = array_slice(array_map(function ($productId, $itemid) use ($products): array {
    $product = $products->firstWhere('id', $productId);

    return [
        'product_id' => $productId,
        'sku' => $product->sku ?? null,
        'name' => $product->name ?? null,
        'new_itemid' => $itemid,
    ];
}, array_keys($pelionUpdates), $pelionUpdates), 0, 10);
$summary['pelion_applied'] = live_apply_updates($pelionUpdates, $commit);
$summary['missing_itemid_after_estimate'] = $summary['missing_itemid_before'] - count($excelUpdates) - count($pelionUpdates);
$summary['examples'] = $examples;

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
