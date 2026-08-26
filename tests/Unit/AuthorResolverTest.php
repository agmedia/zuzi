<?php

namespace Tests\Unit;

use App\Models\Back\Catalog\Author;
use App\Services\Catalog\AuthorResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Throwable;

class AuthorResolverTest extends TestCase
{
    private const CONNECTION = 'author_resolver_test';

    private string $originalConnection;
    private string $originalCacheDriver;
    private string $originalFileCachePath;
    private string $testDirectory;
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        $this->originalCacheDriver = (string) config('cache.default');
        $this->originalFileCachePath = (string) config('cache.stores.file.path');
        $this->testDirectory = sys_get_temp_dir() . '/zuzi-author-resolver-' . bin2hex(random_bytes(8));
        $this->databasePath = $this->testDirectory . '/database.sqlite';

        File::makeDirectory($this->testDirectory . '/cache', 0777, true);
        File::put($this->databasePath, '');

        config([
            'database.default' => self::CONNECTION,
            'database.connections.' . self::CONNECTION => [
                'driver' => 'sqlite',
                'database' => $this->databasePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'file',
            'cache.stores.file.path' => $this->testDirectory . '/cache',
        ]);

        DB::purge(self::CONNECTION);
        app('cache')->forgetDriver('file');
        app('cache')->setDefaultDriver('file');

        Schema::create('authors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('normalized_title', 191)->unique('authors_normalized_title_unique');
            $table->string('letter', 2);
            $table->string('title');
            $table->longText('description')->nullable();
            $table->text('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('image')->default('media/avatars/avatar0.jpg');
            $table->string('lang')->default('hr');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(false);
            $table->boolean('featured')->default(false);
            $table->string('slug');
            $table->string('url');
            $table->unsignedInteger('viewed')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        app('cache')->forgetDriver('file');

        config([
            'database.default' => $this->originalConnection,
            'cache.default' => $this->originalCacheDriver,
            'cache.stores.file.path' => $this->originalFileCachePath,
        ]);
        app('cache')->setDefaultDriver($this->originalCacheDriver);

        File::deleteDirectory($this->testDirectory);

        parent::tearDown();
    }

    public function test_reuses_existing_author_ignoring_case_and_outer_or_repeated_whitespace(): void
    {
        $existingId = DB::table('authors')->insertGetId($this->authorRow(
            'Ivan Ivić',
            'ivan ivić'
        ));

        $resolvedId = app(AuthorResolver::class)->resolve("\u{00A0}IVAN \t  \n IVIĆ\u{00A0}");

        $this->assertSame($existingId, $resolvedId);
        $this->assertSame(1, DB::table('authors')->count());
    }

    public function test_keeps_punctuation_significant_when_matching_authors(): void
    {
        $hyphenatedId = app(AuthorResolver::class)->resolve('Jean-Paul Sartre');
        $spacedId = app(AuthorResolver::class)->resolve('Jean Paul Sartre');

        $this->assertNotSame($hyphenatedId, $spacedId);
        $this->assertSame(2, DB::table('authors')->count());
        $this->assertDatabaseHas('authors', ['normalized_title' => 'jean-paul sartre']);
        $this->assertDatabaseHas('authors', ['normalized_title' => 'jean paul sartre']);
    }

    public function test_uses_only_the_first_comma_separated_author(): void
    {
        $resolvedId = app(AuthorResolver::class)->resolve(
            '  Ursula   K. Le Guin, Octavia E. Butler'
        );

        $this->assertSame(
            $resolvedId,
            app(AuthorResolver::class)->resolve('ursula k. le guin')
        );
        $this->assertDatabaseHas('authors', [
            'id' => $resolvedId,
            'title' => 'Ursula K. Le Guin',
            'normalized_title' => 'ursula k. le guin',
        ]);
        $this->assertDatabaseMissing('authors', ['title' => 'Octavia E. Butler']);
        $this->assertSame(1, DB::table('authors')->count());
    }

    public function test_resolve_name_preserves_a_comma_as_part_of_the_complete_name(): void
    {
        $johnId = app(AuthorResolver::class)->resolveName('  Smith,   John ');
        $janeId = app(AuthorResolver::class)->resolveName('Smith, Jane');

        $this->assertNotSame($johnId, $janeId);
        $this->assertDatabaseHas('authors', [
            'id' => $johnId,
            'title' => 'Smith, John',
            'normalized_title' => 'smith, john',
        ]);
        $this->assertDatabaseHas('authors', [
            'id' => $janeId,
            'title' => 'Smith, Jane',
            'normalized_title' => 'smith, jane',
        ]);
        $this->assertSame(2, DB::table('authors')->count());
    }

    public function test_empty_author_returns_zero_without_creating_a_record(): void
    {
        $this->assertSame(0, app(AuthorResolver::class)->resolve(null));
        $this->assertSame(0, app(AuthorResolver::class)->resolve(" \t\n\u{00A0}"));
        $this->assertSame(0, DB::table('authors')->count());
    }

    public function test_manual_author_validation_reports_a_normalized_duplicate(): void
    {
        $existingId = DB::table('authors')->insertGetId($this->authorRow(
            'Ivan Ivić',
            'ivan ivić'
        ));

        try {
            (new Author())->validateRequest(Request::create('/authors', 'POST', [
                'title' => "\u{00A0}IVAN  \t IVIĆ\u{00A0}",
            ]));

            $this->fail('Normalized duplicate validation should fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Autor s tim imenom već postoji.'],
                $exception->errors()['title']
            );
        }

        $existing = Author::query()->findOrFail($existingId);
        $this->assertSame(
            $existing,
            $existing->validateRequest(Request::create('/authors/' . $existingId, 'PUT', [
                'title' => ' IVAN IVIĆ ',
            ]))
        );
    }

    public function test_parallel_resolution_reuses_one_author_record(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            $this->markTestSkipped('The pcntl extension is required for the concurrency regression test.');
        }

        $startPath = $this->testDirectory . '/start';
        $resultPaths = [
            $this->testDirectory . '/result-0',
            $this->testDirectory . '/result-1',
        ];
        $errorPaths = [
            $this->testDirectory . '/error-0',
            $this->testDirectory . '/error-1',
        ];

        DB::disconnect(self::CONNECTION);
        app('cache')->forgetDriver('file');

        $pids = [];
        foreach ([0, 1] as $worker) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Could not fork the author resolver worker.');

            if ($pid === 0) {
                $this->runResolverWorker(
                    $startPath,
                    $resultPaths[$worker],
                    $errorPaths[$worker],
                    $worker === 0 ? '  TERRY   PRATCHETT ' : 'terry pratchett'
                );
            }

            $pids[] = $pid;
        }

        File::put($startPath, 'go');

        foreach ($pids as $worker => $pid) {
            $status = 0;
            pcntl_waitpid($pid, $status);
            $error = File::exists($errorPaths[$worker])
                ? File::get($errorPaths[$worker])
                : '';

            $this->assertTrue(pcntl_wifexited($status), $error ?: 'Resolver worker did not exit normally.');
            $this->assertSame(0, pcntl_wexitstatus($status), $error);
        }

        $resolvedIds = array_map(function (string $path): int {
            return (int) File::get($path);
        }, $resultPaths);

        DB::purge(self::CONNECTION);

        $this->assertSame($resolvedIds[0], $resolvedIds[1]);
        $this->assertSame(1, DB::table('authors')->count());
        $this->assertDatabaseHas('authors', [
            'id' => $resolvedIds[0],
            'normalized_title' => 'terry pratchett',
        ]);
    }

    private function runResolverWorker(
        string $startPath,
        string $resultPath,
        string $errorPath,
        string $author
    ): void {
        try {
            DB::purge(self::CONNECTION);
            app('cache')->forgetDriver('file');
            DB::statement('PRAGMA busy_timeout = 5000');

            $deadline = microtime(true) + 5;
            while (! File::exists($startPath) && microtime(true) < $deadline) {
                usleep(1000);
            }

            if (! File::exists($startPath)) {
                throw new \RuntimeException('Timed out waiting for the resolver concurrency barrier.');
            }

            $resolvedId = app(AuthorResolver::class)->resolve($author);
            File::put($resultPath, (string) $resolvedId);
            exit(0);
        } catch (Throwable $exception) {
            File::put($errorPath, get_class($exception) . ': ' . $exception->getMessage());
            exit(1);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function authorRow(string $title, string $normalizedTitle): array
    {
        return [
            'normalized_title' => $normalizedTitle,
            'letter' => mb_substr($title, 0, 1),
            'title' => $title,
            'description' => null,
            'meta_title' => $title,
            'meta_description' => null,
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => str_replace(' ', '-', mb_strtolower($title)),
            'url' => '/autor/' . str_replace(' ', '-', mb_strtolower($title)),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
