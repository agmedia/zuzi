<?php

namespace App\Console\Commands;

use App\Models\Back\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuessProductLanguages extends Command
{
    private const FOREIGN_PARENT_ID = 27;
    private const REGIONAL_CATEGORY_ID = 109;
    private const CYRILLIC_CATEGORY_ID = 134;
    private const SUPPORTED_LANGUAGES = [
        'Albanski',
        'Bosanski',
        'Češki',
        'Engleski',
        'Francuski',
        'Hrvatski',
        'Hrvatskosrbski',
        'Mađarski',
        'Njemački',
        'Norveški',
        'Poljski',
        'Portugalski',
        'Ruski',
        'Slovački',
        'Slovenski',
        'Srpski',
        'Španjolski',
        'Švedski',
        'Talijanski',
        'Turski',
    ];

    private const FOREIGN_CATEGORY_LANGUAGE_MAP = [
        28 => 'Srpski',
        49 => 'Njemački',
        55 => 'Engleski',
        75 => 'Slovenski',
        106 => 'Talijanski',
        107 => 'Češki',
        108 => 'Turski',
        126 => 'Francuski',
        154 => 'Španjolski',
        158 => 'Ruski',
        168 => 'Bosanski',
        170 => 'Švedski',
        177 => 'Mađarski',
        178 => 'Slovački',
        179 => 'Norveški',
        181 => 'Portugalski',
        184 => 'Poljski',
        200 => 'Albanski',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:guess-languages
                            {--apply : Upiši jezike u bazu}
                            {--aggressive : Uključi i drugi prolaz po naslovu i opisu}
                            {--compact : Prikaži samo sažetak bez primjera}
                            {--force : Pregazi postojeće language vrijednosti}
                            {--write-sql= : Snimi SQL update skriptu na zadanu putanju}
                            {--limit=0 : Obradi samo prvih N pogodaka}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pogađa jezik artikala iz kategorija, mjesta izdavanja, godine izdanja i opcionalno iz naslova/opisa.';

    private array $originPhraseSets = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->originPhraseSets = $this->buildOriginPhraseSets();

        $should_persist_assignments = $this->option('apply') || filled($this->option('write-sql'));
        $assignments = [];
        $assignment_count = 0;
        $skips = [
            'existing_language' => 0,
            'conflicting_foreign_categories' => 0,
            'ambiguous_origin' => 0,
            'ambiguous_text' => 0,
            'no_match' => 0,
        ];
        $stats = [];
        $samples = [];
        $limit = max(0, (int) $this->option('limit'));
        $aggressive_candidates = [];

        foreach ($this->products() as $product) {
            if (! $this->option('force') && filled($product['language'])) {
                $skips['existing_language']++;
                continue;
            }

            $guess = $this->guessLanguage($product);
            $guess = $this->normalizeGuess($guess);

            if (! $guess['language']) {
                if ($this->option('aggressive') && $this->isAggressiveCandidate($product)) {
                    $aggressive_candidates[] = $product;
                    continue;
                }

                $skips[$guess['skip_reason'] ?? 'no_match']++;
                continue;
            }

            $this->recordAssignment($assignments, $assignment_count, $stats, $samples, $product, $guess, $should_persist_assignments);

            if ($limit && $assignment_count >= $limit) {
                break;
            }
        }

        if ($this->option('aggressive') && (! $limit || $assignment_count < $limit) && count($aggressive_candidates)) {
            foreach (array_chunk($aggressive_candidates, 500) as $candidate_chunk) {
                $descriptions = $this->loadDescriptions(array_column($candidate_chunk, 'id'));

                foreach ($candidate_chunk as $product) {
                    $product['description'] = $descriptions[$product['id']] ?? '';

                    $guess = $this->guessLanguageAggressive($product);
                    $guess = $this->normalizeGuess($guess);

                    if (! $guess['language']) {
                        $skips[$guess['skip_reason'] ?? 'no_match']++;
                        continue;
                    }

                    $this->recordAssignment($assignments, $assignment_count, $stats, $samples, $product, $guess, $should_persist_assignments);

                    if ($limit && $assignment_count >= $limit) {
                        break 2;
                    }
                }
            }
        }

        $this->renderSummary($assignment_count, $stats, $skips, $samples);

        if ($path = $this->option('write-sql')) {
            $written = $this->writeSqlFile($assignments, (string) $path);
            $this->info('SQL skripta snimljena: ' . $written);
        }

        if (! $this->option('apply')) {
            $this->line('Dry run završen. Za upis pokreni s opcijom `--apply`.');
            return self::SUCCESS;
        }

        if (! count($assignments)) {
            $this->warn('Nema pogodaka za upis.');
            return self::SUCCESS;
        }

        $this->applyAssignments($assignments);
        $this->syncLanguageStyles($assignments);

        $this->info('Upis jezika je dovršen.');

        return self::SUCCESS;
    }

    private function products(): LazyCollection
    {
        return DB::table('products as p')
            ->leftJoin('product_category as pc', 'pc.product_id', '=', 'p.id')
            ->select('p.id', 'p.sku', 'p.name', 'p.origin', 'p.year', 'p.language')
            ->selectRaw("GROUP_CONCAT(DISTINCT pc.category_id ORDER BY pc.category_id SEPARATOR ',') AS category_ids")
            ->groupBy('p.id', 'p.sku', 'p.name', 'p.origin', 'p.year', 'p.language')
            ->orderBy('p.id')
            ->cursor()
            ->map(function ($row) {
                $category_ids = collect(explode(',', (string) $row->category_ids))
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                return [
                    'id' => (int) $row->id,
                    'sku' => (string) $row->sku,
                    'name' => (string) $row->name,
                    'origin' => $row->origin ? (string) $row->origin : null,
                    'year' => $row->year ? (string) $row->year : null,
                    'language' => $row->language ? (string) $row->language : null,
                    'category_ids' => $category_ids,
                ];
            });
    }

    private function guessLanguage(array $product): array
    {
        $category_lookup = array_fill_keys($product['category_ids'], true);
        $origin_groups = $this->detectOriginGroups($product['origin']);
        $is_regional = isset($category_lookup[self::REGIONAL_CATEGORY_ID]);
        $is_foreign_branch = $this->hasForeignBranch($product);
        $year = $this->resolveYear($product['year']);

        $foreign_category_languages = [];

        foreach ($product['category_ids'] as $category_id) {
            if (isset(self::FOREIGN_CATEGORY_LANGUAGE_MAP[$category_id])) {
                $foreign_category_languages[self::FOREIGN_CATEGORY_LANGUAGE_MAP[$category_id]] = true;
            }
        }

        if (count($foreign_category_languages) === 1) {
            return [
                'language' => array_key_first($foreign_category_languages),
                'rule' => 'foreign_category_unique',
            ];
        }

        if (count($foreign_category_languages) > 1) {
            return [
                'language' => null,
                'skip_reason' => 'conflicting_foreign_categories',
            ];
        }

        if (! $is_regional && ! $is_foreign_branch) {
            return [
                'language' => 'Hrvatski',
                'rule' => 'default_croatian_outside_special_categories',
            ];
        }

        if ($this->hasAnyGroup($origin_groups, ['serbian']) && $year !== null && $year < 1990) {
            return [
                'language' => 'Hrvatskosrbski',
                'rule' => 'serbian_origin_pre_1990',
            ];
        }

        if ($is_regional && $year !== null && $year < 1990 && $this->hasAnyGroup($origin_groups, ['serbian', 'bosnian', 'montenegrin', 'macedonian', 'croatian'])) {
            return [
                'language' => 'Hrvatskosrbski',
                'rule' => 'regional_pre_1990',
            ];
        }

        if ($this->hasAnyGroup($origin_groups, ['serbian']) && $year !== null && $year >= 1990) {
            return [
                'language' => 'Srpski',
                'rule' => 'serbian_origin_modern',
            ];
        }

        if ($this->hasGroup($origin_groups, 'croatian') && ! $is_foreign_branch && count($origin_groups) === 1) {
            return [
                'language' => 'Hrvatski',
                'rule' => 'croatian_origin',
            ];
        }

        $foreign_origin_map = [
            'english' => 'Engleski',
            'german' => 'Njemački',
            'french' => 'Francuski',
            'italian' => 'Talijanski',
            'spanish' => 'Španjolski',
            'russian' => 'Ruski',
        ];

        $foreign_matches = array_values(array_intersect(array_keys($foreign_origin_map), $origin_groups));

        if ($is_foreign_branch && $this->hasGroup($origin_groups, 'slovenian')) {
            $foreign_matches[] = 'slovenian';
            $foreign_origin_map['slovenian'] = 'Slovenski';
            $foreign_matches = array_values(array_unique($foreign_matches));
        }

        if (count($foreign_matches) === 1) {
            return [
                'language' => $foreign_origin_map[$foreign_matches[0]],
                'rule' => 'foreign_origin',
            ];
        }

        if (count($foreign_matches) > 1 || count($origin_groups) > 1) {
            return [
                'language' => null,
                'skip_reason' => 'ambiguous_origin',
            ];
        }

        return [
            'language' => null,
            'skip_reason' => 'no_match',
        ];
    }

    private function guessLanguageAggressive(array $product): array
    {
        $category_lookup = array_fill_keys($product['category_ids'], true);
        $origin_groups = $this->detectOriginGroups($product['origin']);
        $year = $this->resolveYear($product['year']);
        $raw_text = trim(($product['name'] ?? '') . ' ' . ($product['description'] ?? ''));
        $normalized_text = $this->normalizePhrase($raw_text);

        $explicit_languages = $this->detectExplicitLanguages($normalized_text);

        if (count($explicit_languages) === 1) {
            return [
                'language' => $explicit_languages[0],
                'rule' => 'explicit_text_language',
            ];
        }

        if (count($explicit_languages) > 1) {
            return [
                'language' => null,
                'skip_reason' => 'ambiguous_text',
            ];
        }

        if (
            isset($category_lookup[self::CYRILLIC_CATEGORY_ID])
            && $year !== null
            && $year < 1990
            && $this->hasAnyGroup($origin_groups, ['serbian', 'croatian', 'bosnian', 'montenegrin', 'macedonian'])
        ) {
            return [
                'language' => 'Hrvatskosrbski',
                'rule' => 'cyrillic_yugo_pre_1990',
            ];
        }

        if ($this->hasForeignBranch($product)) {
            $scored = $this->scoreTextLanguages($normalized_text);

            if ($scored['language']) {
                return [
                    'language' => $scored['language'],
                    'rule' => 'text_keyword_language',
                ];
            }

            if ($scored['ambiguous']) {
                return [
                    'language' => null,
                    'skip_reason' => 'ambiguous_text',
                ];
            }
        }

        return [
            'language' => null,
            'skip_reason' => 'no_match',
        ];
    }

    private function resolveYear(?string $year): ?int
    {
        if ($year && preg_match('/^\d{4}$/', $year)) {
            return (int) $year;
        }

        return null;
    }

    private function detectOriginGroups(?string $origin): array
    {
        if (! $origin) {
            return [];
        }

        $phrases = $this->originPhrases($origin);
        $groups = [];

        foreach ($this->originPhraseSets as $group => $set) {
            foreach ($phrases as $phrase) {
                if (isset($set[$phrase])) {
                    $groups[] = $group;
                    break;
                }
            }
        }

        sort($groups);

        return array_values(array_unique($groups));
    }

    private function buildOriginPhraseSets(): array
    {
        return [
            'croatian' => $this->phraseSet(array_merge(
                $this->loadCroatianCities(),
                ['Hrvatska', 'Croatia', 'Republika Hrvatska', 'RH']
            )),
            'serbian' => $this->phraseSet([
                'Beograd',
                'Srbija',
                'Novi Sad',
                'Kragujevac',
                'Gornji Milanovac',
                'Subotica',
                'Zemun',
                'Valjevo',
                'Svilajnac',
                'Kruševac',
                'Novi Banovci',
                'Vojvodina',
                'Niš',
                'Priština',
                'Kraljevo',
                'Pančevo',
            ]),
            'bosnian' => $this->phraseSet([
                'Sarajevo',
                'Mostar',
                'Tuzla',
                'Banja Luka',
                'Bosna i Hercegovina',
            ]),
            'montenegrin' => $this->phraseSet([
                'Podgorica',
                'Cetinje',
                'Crna Gora',
            ]),
            'macedonian' => $this->phraseSet([
                'Skopje',
                'Makedonija',
                'North Macedonia',
            ]),
            'english' => $this->phraseSet([
                'London',
                'New York',
                'Boston',
                'Oxford',
                'Cambridge',
                'Dublin',
                'USA',
                'United States',
                'Great Britain',
                'England',
                'Scotland',
                'Ireland',
                'UK',
            ]),
            'german' => $this->phraseSet([
                'Njemačka',
                'Germany',
                'Berlin',
                'Hamburg',
                'Stuttgart',
                'Koln',
                'Köln',
                'Munchen',
                'München',
                'Frankfurt',
                'Austrija',
                'Austria',
                'Beč',
                'Vienna',
            ]),
            'french' => $this->phraseSet([
                'Francuska',
                'France',
                'Paris',
            ]),
            'italian' => $this->phraseSet([
                'Italija',
                'Italy',
                'Milano',
                'Milan',
                'Rim',
                'Roma',
                'Torino',
                'Bologna',
            ]),
            'spanish' => $this->phraseSet([
                'Barcelona',
                'Madrid',
                'Španjolska',
                'Spain',
                'Espana',
                'España',
            ]),
            'russian' => $this->phraseSet([
                'Moskva',
                'Moscow',
                'Rusija',
                'Russia',
            ]),
            'slovenian' => $this->phraseSet([
                'Ljubljana',
                'Slovenija',
                'Slovenia',
            ]),
        ];
    }

    private function loadCroatianCities(): array
    {
        $path = public_path('assets/hr-places.json');

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! isset($data['places']) || ! is_array($data['places'])) {
            return [];
        }

        return collect($data['places'])
            ->pluck('city')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function phraseSet(array $values): array
    {
        $set = [];

        foreach ($values as $value) {
            $phrase = $this->normalizePhrase($value);

            if ($phrase !== '') {
                $set[$phrase] = true;
            }
        }

        return $set;
    }

    private function originPhrases(string $origin): array
    {
        $normalized = $this->normalizePhrase($origin);

        if ($normalized === '') {
            return [];
        }

        $tokens = explode(' ', $normalized);
        $phrases = [];
        $count = count($tokens);

        for ($start = 0; $start < $count; $start++) {
            $phrase = '';

            for ($end = $start; $end < min($count, $start + 4); $end++) {
                $phrase = trim($phrase . ' ' . $tokens[$end]);
                $phrases[$phrase] = true;
            }
        }

        return array_keys($phrases);
    }

    private function normalizePhrase(string $value): string
    {
        $value = Str::ascii($value);
        $value = mb_strtoupper($value, 'UTF-8');
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function hasAnyGroup(array $origin_groups, array $needles): bool
    {
        return count(array_intersect($origin_groups, $needles)) > 0;
    }

    private function hasGroup(array $origin_groups, string $needle): bool
    {
        return in_array($needle, $origin_groups, true);
    }

    private function recordAssignment(
        array &$assignments,
        int &$assignment_count,
        array &$stats,
        array &$samples,
        array $product,
        array $guess,
        bool $persist_assignment
    ): void
    {
        if ($persist_assignment) {
            $assignments[$product['id']] = [
                'id' => $product['id'],
                'language' => $guess['language'],
            ];
        }

        $assignment_count++;

        $stats[$guess['rule']][$guess['language']] = ($stats[$guess['rule']][$guess['language']] ?? 0) + 1;

        if (! isset($samples[$guess['rule']])) {
            $samples[$guess['rule']] = [];
        }

        if (count($samples[$guess['rule']]) < 5) {
            $samples[$guess['rule']][] = [
                'id' => $product['id'],
                'language' => $guess['language'],
                'name' => Str::limit($product['name'], 80, '...'),
            ];
        }
    }

    private function loadDescriptions(array $ids): array
    {
        $descriptions = [];

        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table('products')
                ->select('id')
                ->selectRaw('LEFT(COALESCE(description, ""), 4000) as description_excerpt')
                ->whereIn('id', $chunk)
                ->get()
                ->each(function ($row) use (&$descriptions) {
                    $descriptions[(int) $row->id] = (string) $row->description_excerpt;
                });
        }

        return $descriptions;
    }

    private function isAggressiveCandidate(array $product): bool
    {
        $interesting_categories = array_merge(
            [self::FOREIGN_PARENT_ID, self::REGIONAL_CATEGORY_ID, self::CYRILLIC_CATEGORY_ID],
            array_keys(self::FOREIGN_CATEGORY_LANGUAGE_MAP)
        );

        return count(array_intersect($product['category_ids'], $interesting_categories)) > 0;
    }

    private function hasForeignBranch(array $product): bool
    {
        if (in_array(self::FOREIGN_PARENT_ID, $product['category_ids'], true)) {
            return true;
        }

        return count(array_intersect($product['category_ids'], array_keys(self::FOREIGN_CATEGORY_LANGUAGE_MAP))) > 0;
    }

    private function detectExplicitLanguages(string $normalized_text): array
    {
        $languages = [];

        foreach ($this->explicitLanguageSignals() as $language => $signals) {
            foreach ($signals as $signal) {
                if (str_contains($normalized_text, $signal)) {
                    $languages[$language] = true;
                    break;
                }
            }
        }

        return array_keys($languages);
    }

    private function explicitLanguageSignals(): array
    {
        return [
            'Engleski' => $this->buildContextualLanguageSignals('ENGLESK', ['ENGLISH LANGUAGE', 'IN ENGLISH']),
            'Njemački' => $this->buildContextualLanguageSignals('NJEMACK', ['DEUTSCH', 'GERMAN LANGUAGE', 'IN GERMAN', 'NEMACKI JEZIK', 'NA NEMACKOM', 'NEMACKO IZDANJE']),
            'Francuski' => $this->buildContextualLanguageSignals('FRANCUSK', ['FRENCH LANGUAGE', 'IN FRENCH']),
            'Talijanski' => $this->buildContextualLanguageSignals('TALIJANSK', ['ITALIAN LANGUAGE', 'IN ITALIAN']),
            'Španjolski' => $this->buildContextualLanguageSignals('SPANJOLSK', ['SPANISH LANGUAGE', 'IN SPANISH']),
            'Ruski' => $this->buildContextualLanguageSignals('RUSK', ['RUSSIAN LANGUAGE', 'IN RUSSIAN']),
            'Slovenski' => $this->buildContextualLanguageSignals('SLOVENSK', ['SLOVENIAN LANGUAGE', 'IN SLOVENIAN']),
            'Češki' => $this->buildContextualLanguageSignals('CESK', ['CZECH LANGUAGE', 'IN CZECH']),
            'Srpski' => $this->buildContextualLanguageSignals('SRPSK', ['SERBIAN LANGUAGE', 'IN SERBIAN']),
            'Bosanski' => $this->buildContextualLanguageSignals('BOSANSK', ['BOSNIAN LANGUAGE', 'IN BOSNIAN']),
            'Mađarski' => $this->buildContextualLanguageSignals('MADARSK', ['HUNGARIAN LANGUAGE', 'IN HUNGARIAN']),
            'Poljski' => $this->buildContextualLanguageSignals('POLJSK', ['POLISH LANGUAGE', 'IN POLISH']),
            'Portugalski' => $this->buildContextualLanguageSignals('PORTUGALSK', ['PORTUGUESE LANGUAGE', 'IN PORTUGUESE']),
            'Švedski' => $this->buildContextualLanguageSignals('SVEDSK', ['SWEDISH LANGUAGE', 'IN SWEDISH']),
            'Norveški' => $this->buildContextualLanguageSignals('NORVESK', ['NORWEGIAN LANGUAGE', 'IN NORWEGIAN']),
            'Turski' => $this->buildContextualLanguageSignals('TURSK', ['TURKISH LANGUAGE', 'IN TURKISH']),
            'Albanski' => $this->buildContextualLanguageSignals('ALBANSK', ['ALBANIAN LANGUAGE', 'IN ALBANIAN']),
            'Slovački' => $this->buildContextualLanguageSignals('SLOVACK', ['SLOVAK LANGUAGE', 'IN SLOVAK']),
        ];
    }

    private function buildContextualLanguageSignals(string $stem, array $extra = []): array
    {
        return array_values(array_unique(array_merge([
            'NA ' . $stem . 'OM',
            $stem . 'I JEZIK',
            $stem . 'OM JEZIKU',
            $stem . 'O IZDANJE',
            'IZDANJE NA ' . $stem . 'OM',
            'TEKST NA ' . $stem . 'OM',
        ], $extra)));
    }

    private function scoreTextLanguages(string $normalized_text): array
    {
        $scores = [];

        foreach ($this->textLanguageSignals() as $language => $signals) {
            $score = 0;

            foreach ($signals as $signal) {
                if ($this->containsToken($normalized_text, $signal)) {
                    $score++;
                }
            }

            if ($score > 0) {
                $scores[$language] = $score;
            }
        }

        if (! count($scores)) {
            return [
                'language' => null,
                'ambiguous' => false,
            ];
        }

        arsort($scores);
        $best_language = array_key_first($scores);
        $best_score = $scores[$best_language];
        $second_score = count($scores) > 1 ? array_values($scores)[1] : 0;

        if ($best_score < 2) {
            return [
                'language' => null,
                'ambiguous' => false,
            ];
        }

        if ($second_score >= $best_score) {
            return [
                'language' => null,
                'ambiguous' => true,
            ];
        }

        return [
            'language' => $best_language,
            'ambiguous' => false,
        ];
    }

    private function textLanguageSignals(): array
    {
        return [
            'Engleski' => ['THE', 'AND', 'GUIDE', 'NOVEL', 'WORLD', 'OFFICIAL', 'CLASSIC', 'CLASSICS', 'BOOK', 'HISTORY', 'SELECTED', 'BIOGRAPHY'],
            'Njemački' => ['DER', 'DIE', 'DAS', 'UND', 'NACH', 'WIE', 'VON', 'KUNST', 'GESCHICHTE', 'OSTERREICH', 'ZEUGNISSEN', 'EIGENEN'],
            'Francuski' => ['LIVRE', 'HISTOIRE', 'PEINTURE', 'DECORATION', 'CHANSON', 'TRADITIONNELLE', 'NAISSANCE', 'NOUVELLE', 'AUTRICHE', 'SCULPTURE', 'GOTHIQUE'],
        ];
    }

    private function containsToken(string $normalized_text, string $signal): bool
    {
        return preg_match('/(?:^|\s)' . preg_quote($signal, '/') . '(?:$|\s)/', $normalized_text) === 1;
    }

    private function normalizeGuess(array $guess): array
    {
        if (! ($guess['language'] ?? null)) {
            return $guess;
        }

        if (in_array($guess['language'], self::SUPPORTED_LANGUAGES, true)) {
            return $guess;
        }

        return [
            'language' => null,
            'skip_reason' => 'no_match',
        ];
    }

    private function renderSummary(int $assignment_count, array $stats, array $skips, array $samples): void
    {
        $this->info('Predloženi jezici: ' . $assignment_count);

        $summary_rows = [];

        foreach ($stats as $rule => $languages) {
            $summary_rows[] = [
                'Pravilo' => $rule,
                'Jezici' => collect($languages)
                    ->map(fn ($count, $language) => $language . ' (' . $count . ')')
                    ->implode(', '),
                'Ukupno' => array_sum($languages),
            ];
        }

        if (count($summary_rows)) {
            $this->table(['Pravilo', 'Jezici', 'Ukupno'], $summary_rows);
        }

        $skip_rows = [];

        foreach ($skips as $reason => $count) {
            $skip_rows[] = [
                'Razlog' => $reason,
                'Broj' => $count,
            ];
        }

        $this->table(['Razlog', 'Broj'], $skip_rows);

        if ($this->option('compact')) {
            return;
        }

        foreach ($samples as $rule => $rows) {
            $this->line('Primjeri za ' . $rule . ':');
            $this->table(['ID', 'Jezik', 'Naziv'], $rows);
        }
    }

    private function writeSqlFile(array $assignments, string $path): string
    {
        $target = Str::startsWith($path, ['/']) ? $path : base_path($path);
        $directory = dirname($target);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $lines = [
            '-- Generirano komandom products:guess-languages',
            '-- Datum: ' . now()->toDateTimeString(),
            '-- Ukupno artikala: ' . count($assignments),
            '',
            'START TRANSACTION;',
            '',
        ];

        collect($assignments)
            ->groupBy('language')
            ->sortKeys()
            ->each(function (Collection $rows, string $language) use (&$lines) {
                $ids = $rows->pluck('id')->sort()->values()->all();
                $chunks = array_chunk($ids, 1000);

                $lines[] = '-- ' . $language . ' (' . count($ids) . ')';

                foreach ($chunks as $chunk) {
                    $lines[] = sprintf(
                        "UPDATE `products` SET `language` = '%s', `updated_at` = NOW() WHERE `id` IN (%s);",
                        str_replace("'", "''", $language),
                        implode(',', $chunk)
                    );
                }

                $lines[] = '';
            });

        $lines[] = 'COMMIT;';
        $lines[] = '';

        file_put_contents($target, implode(PHP_EOL, $lines));

        return $target;
    }

    private function applyAssignments(array $assignments): void
    {
        foreach (array_chunk(array_values($assignments), 500) as $chunk) {
            $ids = [];
            $bindings = [];
            $cases = [];

            foreach ($chunk as $row) {
                $ids[] = (int) $row['id'];
                $cases[] = 'WHEN ? THEN ?';
                $bindings[] = (int) $row['id'];
                $bindings[] = $row['language'];
            }

            $id_placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = 'UPDATE `products` SET `language` = CASE `id` ' . implode(' ', $cases) . ' END, `updated_at` = NOW() WHERE `id` IN (' . $id_placeholders . ')';

            DB::update($sql, array_merge($bindings, $ids));
        }
    }

    private function syncLanguageStyles(array $assignments): void
    {
        collect($assignments)
            ->pluck('language')
            ->unique()
            ->sort()
            ->each(function ($language) {
                Settings::setProduct('language_styles', $language);
            });
    }
}
