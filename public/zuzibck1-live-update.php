<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const ZUZIBCK1_ACCESS_TOKEN = '6b504abc29f0a73aa03c8b239d49cf62cad1';
const ZUZIBCK1_TEMP_TABLE = 'tmp_zuzibck1_live_updates';

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('memory_limit', '768M');
set_time_limit(0);

function zuzi_request_token(): string
{
    if (isset($_POST['token'])) {
        return (string) $_POST['token'];
    }

    return isset($_GET['token']) ? (string) $_GET['token'] : '';
}

function zuzi_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function zuzi_render_metric(string $label, $value): string
{
    return '<div class="metric"><span>' . zuzi_h($label) . '</span><strong>' . zuzi_h($value) . '</strong></div>';
}

function zuzi_cell($value): string
{
    if ($value === null || $value === '') {
        return '<span class="muted">-</span>';
    }

    return zuzi_h($value);
}

function zuzi_header(string $title): void
{
    echo '<!doctype html><html lang="hr"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . zuzi_h($title) . '</title>';
    echo '<style>
        :root { color-scheme: light; --bg:#f6f7f9; --panel:#fff; --text:#1f2933; --muted:#637083; --line:#d9dee7; --brand:#0f766e; --danger:#b42318; --warn:#a15c07; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; line-height:1.45; }
        main { max-width:1160px; margin:0 auto; padding:32px 18px 48px; }
        h1 { margin:0 0 8px; font-size:28px; letter-spacing:0; }
        h2 { margin:26px 0 10px; font-size:18px; letter-spacing:0; }
        p { margin:8px 0; }
        .panel { background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:18px; box-shadow:0 1px 2px rgba(15,23,42,.05); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:10px; margin-top:16px; }
        .metric { border:1px solid var(--line); border-radius:8px; padding:12px; background:#fbfcfd; min-height:78px; }
        .metric span { display:block; color:var(--muted); font-size:13px; }
        .metric strong { display:block; font-size:24px; margin-top:6px; word-break:break-word; }
        .notice { border-left:4px solid var(--brand); background:#ecfdf5; padding:12px 14px; border-radius:6px; margin:16px 0; }
        .warning { border-left-color:var(--warn); background:#fffbeb; }
        .danger { border-left-color:var(--danger); background:#fff1f2; }
        .muted { color:var(--muted); }
        .actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:18px; }
        button, .link-button { border:0; border-radius:8px; padding:12px 16px; font-weight:700; font-size:15px; cursor:pointer; text-decoration:none; display:inline-block; }
        button { background:var(--brand); color:#fff; }
        button:disabled { background:#9aa6b2; cursor:not-allowed; }
        .link-button { background:#e6eaf0; color:#1f2933; }
        table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); border-radius:8px; overflow:hidden; display:block; overflow-x:auto; }
        th, td { text-align:left; border-bottom:1px solid var(--line); padding:9px 10px; white-space:nowrap; vertical-align:top; }
        th { background:#f1f4f8; color:#425466; font-size:13px; }
        tr:last-child td { border-bottom:0; }
        code { background:#eef2f7; border-radius:5px; padding:2px 5px; }
        .ok { color:var(--brand); font-weight:700; }
        .bad { color:var(--danger); font-weight:700; }
    </style></head><body><main>';
}

function zuzi_footer(): void
{
    echo '</main></body></html>';
}

function zuzi_parse_csv(string $csvPath): array
{
    $handle = fopen($csvPath, 'rb');

    if (! $handle) {
        throw new RuntimeException('Ne mogu otvoriti CSV: ' . $csvPath);
    }

    $header = fgetcsv($handle, 0, ';', '"', '\\');

    if ($header !== ['id', 'name', 'polica', 'delivery_24h']) {
        throw new RuntimeException('CSV header nije očekivan. Dobiveno: ' . json_encode($header, JSON_UNESCAPED_UNICODE));
    }

    $rows = [];
    $seen = [];
    $line = 1;
    $stats = [
        'csv_rows' => 0,
        'rows_with_action' => 0,
        'polica_candidates' => 0,
        'delivery_24h_candidates' => 0,
        'invalid_rows' => 0,
        'invalid_ids' => 0,
        'duplicate_ids' => 0,
        'invalid_delivery_24h' => 0,
    ];
    $examples = [
        'invalid_delivery_24h' => [],
        'duplicates' => [],
        'invalid_rows' => [],
    ];

    while (($data = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
        $line++;
        $stats['csv_rows']++;

        if (count($data) < 4) {
            $stats['invalid_rows']++;

            if (count($examples['invalid_rows']) < 10) {
                $examples['invalid_rows'][] = ['line' => $line, 'columns' => count($data)];
            }

            continue;
        }

        if (count($data) > 4) {
            $idRaw = $data[0];
            $name = implode(';', array_slice($data, 1, -2));
            $policaRaw = $data[count($data) - 2];
            $deliveryRaw = $data[count($data) - 1];
        } else {
            $idRaw = $data[0];
            $name = $data[1];
            $policaRaw = $data[2];
            $deliveryRaw = $data[3];
        }

        $idRaw = trim((string) $idRaw);

        if ($idRaw === '' || ! ctype_digit($idRaw)) {
            $stats['invalid_ids']++;
            continue;
        }

        $id = (int) $idRaw;

        if (isset($seen[$id])) {
            $stats['duplicate_ids']++;

            if (count($examples['duplicates']) < 10) {
                $examples['duplicates'][] = [
                    'line' => $line,
                    'id' => $id,
                    'name' => trim((string) $name),
                ];
            }

            continue;
        }

        $seen[$id] = true;

        $polica = trim((string) $policaRaw);
        $hasPolica = $polica !== '' && strcasecmp($polica, 'NULL') !== 0;
        $deliveryRaw = trim((string) $deliveryRaw);
        $delivery24h = null;

        if ($deliveryRaw !== '0') {
            if (ctype_digit($deliveryRaw) && (int) $deliveryRaw <= 255) {
                $delivery24h = (int) $deliveryRaw;
            } else {
                $stats['invalid_delivery_24h']++;

                if (count($examples['invalid_delivery_24h']) < 10) {
                    $examples['invalid_delivery_24h'][] = [
                        'line' => $line,
                        'id' => $id,
                        'name' => trim((string) $name),
                        'polica' => $polica,
                        'delivery_24h' => $deliveryRaw,
                    ];
                }
            }
        }

        if ($hasPolica) {
            $stats['polica_candidates']++;
        }

        if ($delivery24h !== null && $delivery24h !== 0) {
            $stats['delivery_24h_candidates']++;
        }

        if (! $hasPolica && ($delivery24h === null || $delivery24h === 0)) {
            continue;
        }

        $rows[] = [
            'id' => $id,
            'csv_line' => $line,
            'csv_name' => mb_substr(trim((string) $name), 0, 255, 'UTF-8'),
            'polica' => $hasPolica ? $polica : null,
            'delivery_24h' => $delivery24h !== null && $delivery24h !== 0 ? $delivery24h : null,
        ];
        $stats['rows_with_action']++;
    }

    fclose($handle);

    return ['rows' => $rows, 'stats' => $stats, 'examples' => $examples];
}

function zuzi_validate_schema(): void
{
    if (! Schema::hasTable('products')) {
        throw new RuntimeException('Nedostaje tablica products.');
    }

    foreach (['id', 'sku', 'name', 'polica', 'delivery_24h', 'updated_at'] as $column) {
        if (! Schema::hasColumn('products', $column)) {
            throw new RuntimeException('Nedostaje kolona products.' . $column . '.');
        }
    }
}

function zuzi_create_temp_table(array $rows): void
{
    DB::statement('DROP TEMPORARY TABLE IF EXISTS `' . ZUZIBCK1_TEMP_TABLE . '`');
    DB::statement(
        'CREATE TEMPORARY TABLE `' . ZUZIBCK1_TEMP_TABLE . '` (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            `csv_line` INT UNSIGNED NOT NULL,
            `csv_name` VARCHAR(255) NULL,
            `polica` VARCHAR(64) NULL,
            `delivery_24h` TINYINT UNSIGNED NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    foreach (array_chunk($rows, 1000) as $chunk) {
        DB::table(ZUZIBCK1_TEMP_TABLE)->insert($chunk);
    }
}

function zuzi_change_condition(): string
{
    return "((u.polica IS NOT NULL AND COALESCE(p.polica, '') <> u.polica) OR (u.delivery_24h IS NOT NULL AND p.delivery_24h <> u.delivery_24h))";
}

function zuzi_count_sql(string $sql): int
{
    $rows = DB::select($sql);

    return isset($rows[0]->total) ? (int) $rows[0]->total : 0;
}

function zuzi_build_summary(array $parsed): array
{
    $condition = zuzi_change_condition();
    $stats = $parsed['stats'];

    $tempRows = zuzi_count_sql('SELECT COUNT(*) AS total FROM `' . ZUZIBCK1_TEMP_TABLE . '`');
    $productCount = DB::table('products')->count();
    $matchedRows = zuzi_count_sql('SELECT COUNT(*) AS total FROM `' . ZUZIBCK1_TEMP_TABLE . '` u INNER JOIN products p ON p.id = u.id');
    $changedRows = zuzi_count_sql('SELECT COUNT(*) AS total FROM `' . ZUZIBCK1_TEMP_TABLE . '` u INNER JOIN products p ON p.id = u.id WHERE ' . $condition);
    $changedPolica = zuzi_count_sql("SELECT COUNT(*) AS total FROM `" . ZUZIBCK1_TEMP_TABLE . "` u INNER JOIN products p ON p.id = u.id WHERE u.polica IS NOT NULL AND COALESCE(p.polica, '') <> u.polica");
    $changedDelivery = zuzi_count_sql('SELECT COUNT(*) AS total FROM `' . ZUZIBCK1_TEMP_TABLE . '` u INNER JOIN products p ON p.id = u.id WHERE u.delivery_24h IS NOT NULL AND p.delivery_24h <> u.delivery_24h');

    return [
        'database' => DB::connection()->getDatabaseName(),
        'app_url' => (string) config('app.url'),
        'app_env' => (string) config('app.env'),
        'products_total' => (int) $productCount,
        'csv_rows' => $stats['csv_rows'],
        'rows_with_action' => $stats['rows_with_action'],
        'temp_rows' => $tempRows,
        'matched_rows' => $matchedRows,
        'missing_products' => $tempRows - $matchedRows,
        'unchanged_rows' => $matchedRows - $changedRows,
        'changed_rows' => $changedRows,
        'changed_polica' => $changedPolica,
        'changed_delivery_24h' => $changedDelivery,
        'polica_candidates' => $stats['polica_candidates'],
        'delivery_24h_candidates' => $stats['delivery_24h_candidates'],
        'invalid_rows' => $stats['invalid_rows'],
        'invalid_ids' => $stats['invalid_ids'],
        'duplicate_ids' => $stats['duplicate_ids'],
        'invalid_delivery_24h' => $stats['invalid_delivery_24h'],
    ];
}

function zuzi_examples(): array
{
    $condition = zuzi_change_condition();

    return [
        'changed' => DB::select(
            'SELECT p.id, p.sku, p.name, u.csv_line, p.polica AS old_polica, u.polica AS new_polica,
                    p.delivery_24h AS old_delivery_24h, u.delivery_24h AS new_delivery_24h
             FROM `' . ZUZIBCK1_TEMP_TABLE . '` u
             INNER JOIN products p ON p.id = u.id
             WHERE ' . $condition . '
             ORDER BY p.id
             LIMIT 10'
        ),
        'missing' => DB::select(
            'SELECT u.id, u.csv_line, u.csv_name, u.polica, u.delivery_24h
             FROM `' . ZUZIBCK1_TEMP_TABLE . '` u
             LEFT JOIN products p ON p.id = u.id
             WHERE p.id IS NULL
             ORDER BY u.id
             LIMIT 10'
        ),
    ];
}

function zuzi_write_backup(string $root): string
{
    $backupPath = $root . '/storage/app/zuzibck1-live-update-backup-' . date('Ymd-His') . '.csv';
    $handle = fopen($backupPath, 'wb');

    if (! $handle) {
        throw new RuntimeException('Ne mogu kreirati backup: ' . $backupPath);
    }

    fputcsv($handle, ['id', 'sku', 'name', 'old_polica', 'new_polica', 'old_delivery_24h', 'new_delivery_24h', 'csv_line'], ';', '"', '\\');

    $condition = zuzi_change_condition();
    DB::table(ZUZIBCK1_TEMP_TABLE . ' as u')
        ->join('products as p', 'p.id', '=', 'u.id')
        ->whereRaw($condition)
        ->select(
            'p.id',
            'p.sku',
            'p.name',
            'p.polica as old_polica',
            'u.polica as new_polica',
            'p.delivery_24h as old_delivery_24h',
            'u.delivery_24h as new_delivery_24h',
            'u.csv_line'
        )
        ->orderBy('p.id')
        ->chunk(1000, function ($rows) use ($handle): void {
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->sku,
                    $row->name,
                    $row->old_polica,
                    $row->new_polica,
                    $row->old_delivery_24h,
                    $row->new_delivery_24h,
                    $row->csv_line,
                ], ';', '"', '\\');
            }
        });

    fclose($handle);

    return $backupPath;
}

function zuzi_apply_updates(): int
{
    $condition = zuzi_change_condition();

    return DB::transaction(function () use ($condition): int {
        return DB::affectingStatement(
            'UPDATE products p
             INNER JOIN `' . ZUZIBCK1_TEMP_TABLE . '` u ON u.id = p.id
             SET
                p.polica = CASE
                    WHEN u.polica IS NOT NULL AND COALESCE(p.polica, \'\') <> u.polica THEN u.polica
                    ELSE p.polica
                END,
                p.delivery_24h = CASE
                    WHEN u.delivery_24h IS NOT NULL AND p.delivery_24h <> u.delivery_24h THEN u.delivery_24h
                    ELSE p.delivery_24h
                END,
                p.updated_at = NOW()
             WHERE ' . $condition
        );
    });
}

function zuzi_render_table(string $title, array $rows, array $columns): void
{
    echo '<h2>' . zuzi_h($title) . '</h2>';

    if (! $rows) {
        echo '<p class="muted">Nema primjera.</p>';
        return;
    }

    echo '<table><thead><tr>';

    foreach ($columns as $label => $key) {
        echo '<th>' . zuzi_h($label) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($columns as $key) {
            $value = is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);
            echo '<td>' . zuzi_cell($value) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table>';
}

if (! hash_equals(ZUZIBCK1_ACCESS_TOKEN, zuzi_request_token())) {
    http_response_code(403);
    zuzi_header('Zuzibck1 update - zabranjeno');
    echo '<div class="panel danger"><h1>403</h1><p>Nedostaje ili je krivi token.</p></div>';
    zuzi_footer();
    exit;
}

$root = dirname(__DIR__);
$csvPath = __DIR__ . '/zuzibck1.csv';
$apply = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply']) && $_POST['apply'] === '1');
$startedAt = microtime(true);

try {
    require $root . '/vendor/autoload.php';

    $app = require $root . '/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if (! is_file($csvPath)) {
        throw new RuntimeException('CSV nije pronađen. Uploadaj ga kao public/zuzibck1.csv na live.');
    }

    zuzi_validate_schema();
    $parsed = zuzi_parse_csv($csvPath);
    zuzi_create_temp_table($parsed['rows']);
    $summary = zuzi_build_summary($parsed);
    $examples = zuzi_examples();
    $result = null;

    if ($apply) {
        if (preg_match('/(\.test\b|localhost|127\.0\.0\.1)/i', $summary['app_url'])) {
            throw new RuntimeException('Safety stop: APP_URL izgleda lokalno (' . $summary['app_url'] . '). Ovaj runner je namijenjen za live.');
        }

        $backupPath = zuzi_write_backup($root);
        $updatedRows = zuzi_apply_updates();
        $cacheClear = 'nije pokrenuto';

        try {
            Artisan::call('cache:clear');
            $cacheClear = trim(Artisan::output()) ?: 'cache:clear OK';
        } catch (Throwable $cacheException) {
            $cacheClear = 'cache:clear greška: ' . $cacheException->getMessage();
        }

        $result = [
            'backup_path' => $backupPath,
            'updated_rows' => $updatedRows,
            'cache_clear' => $cacheClear,
        ];
    }

    zuzi_header('Zuzibck1 live update');
    echo '<div class="panel">';
    echo '<h1>Zuzibck1 live update</h1>';
    echo '<p class="muted">CSV: <code>public/zuzibck1.csv</code> | DB: <code>' . zuzi_h($summary['database']) . '</code> | APP_URL: <code>' . zuzi_h($summary['app_url']) . '</code> | APP_ENV: <code>' . zuzi_h($summary['app_env']) . '</code></p>';

    if ($apply && $result) {
        echo '<div class="notice"><p><span class="ok">Gotovo.</span> Upisano redova: <strong>' . zuzi_h($result['updated_rows']) . '</strong>.</p>';
        echo '<p>Backup starih vrijednosti: <code>' . zuzi_h($result['backup_path']) . '</code></p>';
        echo '<p>Cache: <code>' . zuzi_h($result['cache_clear']) . '</code></p></div>';
    } else {
        echo '<div class="notice warning"><p>Ovo je dry-run. Upis će se dogoditi tek nakon klika na gumb ispod.</p>';
        echo '<p><code>polica</code> se upisuje samo kada u CSV-u nije <code>NULL</code> ili prazno. <code>delivery_24h</code> se upisuje samo kada u CSV-u nije <code>0</code>.</p></div>';
    }

    echo '<div class="grid">';
    echo zuzi_render_metric('Proizvoda u bazi', $summary['products_total']);
    echo zuzi_render_metric('CSV redova', $summary['csv_rows']);
    echo zuzi_render_metric('Redova za provjeru', $summary['rows_with_action']);
    echo zuzi_render_metric('Nađeno u bazi', $summary['matched_rows']);
    echo zuzi_render_metric('Nema proizvoda', $summary['missing_products']);
    echo zuzi_render_metric('Promijenit će se redova', $summary['changed_rows']);
    echo zuzi_render_metric('Promjena polica', $summary['changed_polica']);
    echo zuzi_render_metric('Promjena delivery_24h', $summary['changed_delivery_24h']);
    echo zuzi_render_metric('Bez promjene', $summary['unchanged_rows']);
    echo zuzi_render_metric('CSV polica kandidati', $summary['polica_candidates']);
    echo zuzi_render_metric('CSV delivery kandidati', $summary['delivery_24h_candidates']);
    echo zuzi_render_metric('Nevaljan delivery_24h', $summary['invalid_delivery_24h']);
    echo '</div>';

    if (! $apply) {
        echo '<form class="actions" method="post" onsubmit="return confirm(\'Upisati promjene na live bazu?\');">';
        echo '<input type="hidden" name="token" value="' . zuzi_h(ZUZIBCK1_ACCESS_TOKEN) . '">';
        echo '<input type="hidden" name="apply" value="1">';
        echo '<button type="submit"' . ($summary['changed_rows'] > 0 ? '' : ' disabled') . '>Upiši promjene na live bazu</button>';
        echo '<a class="link-button" href="?token=' . zuzi_h(ZUZIBCK1_ACCESS_TOKEN) . '">Osvježi dry-run</a>';
        echo '</form>';
    }

    echo '<p class="muted">Trajanje: ' . zuzi_h(number_format(microtime(true) - $startedAt, 2)) . ' s</p>';
    echo '</div>';

    zuzi_render_table('Primjeri promjena', $examples['changed'], [
        'ID' => 'id',
        'SKU' => 'sku',
        'Naziv' => 'name',
        'Stara polica' => 'old_polica',
        'Nova polica' => 'new_polica',
        'Stari delivery_24h' => 'old_delivery_24h',
        'Novi delivery_24h' => 'new_delivery_24h',
        'CSV line' => 'csv_line',
    ]);

    zuzi_render_table('CSV redovi bez proizvoda u bazi', $examples['missing'], [
        'ID' => 'id',
        'CSV line' => 'csv_line',
        'Naziv iz CSV-a' => 'csv_name',
        'Polica' => 'polica',
        'Delivery 24h' => 'delivery_24h',
    ]);

    zuzi_render_table('Nevaljan delivery_24h iz CSV-a', $parsed['examples']['invalid_delivery_24h'], [
        'CSV line' => 'line',
        'ID' => 'id',
        'Naziv' => 'name',
        'Polica' => 'polica',
        'Delivery 24h' => 'delivery_24h',
    ]);

    zuzi_footer();
} catch (Throwable $exception) {
    http_response_code(500);
    zuzi_header('Zuzibck1 update - greška');
    echo '<div class="panel danger"><h1>Greška</h1><p>' . zuzi_h($exception->getMessage()) . '</p>';
    echo '<p class="muted">Nijedan upis nije napravljen ako se greška dogodila prije gumba ili prije transakcije updatea.</p></div>';
    zuzi_footer();
}
